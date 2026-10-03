<?php

namespace RadThemes\AlpCrm\Automations;

use Illuminate\Support\Str;
use RadThemes\AlpCrm\Events\CrmEvent;
use RadThemes\AlpCrm\Models\Automation;
use RadThemes\AlpCrm\Models\AutomationRun;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Segment;
use Throwable;

/**
 * Starts automations when CRM events happen and runs their (possibly delayed) actions.
 */
class Engine
{
    /**
     * Actions can trigger further events (e.g. "add tag" → "contact.tagged"); stop runaway chains.
     */
    private static int $depth = 0;

    private const MAX_DEPTH = 3;

    public function handle(CrmEvent $event): void
    {
        if (self::$depth >= self::MAX_DEPTH) {
            return;
        }

        $automations = Automation::where('active', true)->where('trigger', $event->name)->get();

        foreach ($automations as $automation) {
            if (self::matches($automation, $event)) {
                self::start($automation, $event);
            }
        }
    }

    public static function matches(Automation $automation, CrmEvent $event): bool
    {
        $options = (array) $automation->trigger_options;

        $optionsMatch = match ($event->name) {
            'contact.tagged' => empty($options['tag']) || in_array(Str::slug($options['tag']), array_map(fn ($tag) => Str::slug($tag), (array) ($event->context['tags'] ?? [])), true),
            'contact.status_changed' => empty($options['status']) || ($event->context['to'] ?? null) === $options['status'],
            'form.submitted' => empty($options['form']) || ($event->context['form'] ?? null) === $options['form'],
            default => true,
        };

        if (! $optionsMatch) {
            return false;
        }

        if (! $automation->conditions) {
            return true;
        }

        return $event->contact
            && Segment::applyTo(Contact::whereKey($event->contact->id), $automation->conditions, $automation->match)->exists();
    }

    public static function start(Automation $automation, CrmEvent $event): void
    {
        $context = ['event' => $event->name, 'payload' => $event->payload, 'context' => $event->context];
        $runAt = now();

        foreach (array_values((array) $automation->actions) as $step => $action) {
            $runAt = $runAt->copy()->addSeconds(Automation::delaySeconds($action));

            AutomationRun::create([
                'automation_id' => $automation->id,
                'contact_id' => $event->contact?->id,
                'step' => $step,
                'run_at' => $runAt,
                'context' => $context,
            ]);
        }

        $automation->forceFill(['runs_count' => $automation->runs_count + 1, 'last_run_at' => now()])->saveQuietly();

        self::runDue($automation->id);
    }

    /**
     * Run pending actions that are due (all automations, or one). Called by the scheduler every minute.
     */
    public static function runDue(?int $automationId = null): int
    {
        $runs = AutomationRun::with(['automation', 'contact'])
            ->where('status', 'pending')
            ->where('run_at', '<=', now())
            ->when($automationId, fn ($query) => $query->where('automation_id', $automationId))
            ->orderBy('run_at')->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($runs as $run) {
            self::execute($run);
        }

        return $runs->count();
    }

    private static function execute(AutomationRun $run): void
    {
        // Claim the run so overlapping workers don't repeat it.
        if (! AutomationRun::whereKey($run->id)->where('status', 'pending')->update(['status' => 'running'])) {
            return;
        }

        $automation = $run->automation;
        $action = $automation?->actions[$run->step] ?? null;

        if (! $automation || ! $automation->active || ! $action) {
            $run->update(['status' => 'skipped', 'result' => __('Automation paused or changed')]);

            return;
        }

        if ($run->contact_id && ! $run->contact) {
            $run->update(['status' => 'skipped', 'result' => __('Contact deleted')]);

            return;
        }

        self::$depth++;

        try {
            $result = Actions::run($action, $run->contact, (array) $run->context);
            $run->update(['status' => 'done', 'result' => $result]);
            $run->contact?->logActivity('automation', __('Automation “:name”: :result', ['name' => $automation->name, 'result' => $result]), ['automation_id' => $automation->id]);
        } catch (SkipAction $e) {
            $run->update(['status' => 'skipped', 'result' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            $run->update(['status' => 'failed', 'result' => Str::limit($e->getMessage(), 500)]);
        } finally {
            self::$depth--;
        }
    }
}
