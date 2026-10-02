<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Automations\Actions;
use RadThemes\RadpackCrm\Automations\Recipes;
use RadThemes\RadpackCrm\Events\CrmEvent;
use RadThemes\RadpackCrm\Models\Automation;
use RadThemes\RadpackCrm\Models\AutomationRun;
use RadThemes\RadpackCrm\Models\EmailTemplate;
use RadThemes\RadpackCrm\Models\Segment;
use Statamic\Facades\Form;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class AutomationsController extends CpController
{
    public function index(): Response
    {
        $this->authorize('view crm');

        $events = CrmEvent::names();
        $types = Actions::types();

        return Inertia::render('radpack-crm::Automations/Index', [
            'automations' => Automation::orderBy('name')->get()->map(fn (Automation $automation) => [
                'id' => $automation->id,
                'name' => $automation->name,
                'active' => $automation->active,
                'trigger' => $events[$automation->trigger] ?? $automation->trigger,
                'actions' => collect($automation->actions)->map(fn ($action) => $types[$action['type'] ?? ''] ?? '?')->all(),
                'runs_count' => $automation->runs_count,
                'last_run_at' => $automation->last_run_at?->toIso8601String(),
                'edit_url' => cp_route('radpack-crm.automations.edit', $automation),
                'toggle_url' => cp_route('radpack-crm.automations.toggle', $automation),
                'destroy_url' => cp_route('radpack-crm.automations.destroy', $automation),
            ]),
            'recipes' => collect(Recipes::all())->map(fn ($recipe, $key) => [
                'label' => $recipe['label'],
                'description' => $recipe['description'],
                'icon' => $recipe['icon'],
                'url' => cp_route('radpack-crm.automations.create', ['recipe' => $key]),
            ])->values(),
            'createUrl' => cp_route('radpack-crm.automations.create'),
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('edit crm');

        $values = Recipes::all()[$request->query('recipe')]['values'] ?? [];

        return $this->editor(new Automation(array_merge(['name' => '', 'trigger' => 'contact.created', 'match' => 'all', 'conditions' => [], 'actions' => [['type' => 'add_tag', 'tags' => '', 'delay' => 0, 'delay_unit' => 'minutes']], 'active' => true], $values)));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('edit crm');

        Automation::create($this->validated($request));

        return redirect()->to(cp_route('radpack-crm.automations.index'))->with('success', __('Automation saved'));
    }

    public function edit(Automation $automation): Response
    {
        $this->authorize('edit crm');

        return $this->editor($automation);
    }

    public function update(Request $request, Automation $automation): RedirectResponse
    {
        $this->authorize('edit crm');

        $automation->update($this->validated($request));

        return redirect()->to(cp_route('radpack-crm.automations.index'))->with('success', __('Automation saved'));
    }

    public function toggle(Automation $automation): RedirectResponse
    {
        $this->authorize('edit crm');

        $automation->update(['active' => ! $automation->active]);

        return back();
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $this->authorize('delete crm');

        $automation->delete();

        return redirect()->to(cp_route('radpack-crm.automations.index'));
    }

    private function editor(Automation $automation): Response
    {
        return Inertia::render('radpack-crm::Automations/Edit', Segment::editorOptions() + [
            'title' => $automation->exists ? $automation->name : __('Create Automation'),
            'values' => [
                'name' => $automation->name,
                'active' => $automation->active ?? true,
                'trigger' => $automation->trigger,
                'trigger_options' => (object) ($automation->trigger_options ?? []),
                'match' => $automation->match ?? 'all',
                'conditions' => array_values((array) $automation->conditions),
                'actions' => array_values((array) $automation->actions),
            ],
            'triggers' => collect(CrmEvent::names())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'actionTypes' => collect(Actions::types())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'templates' => EmailTemplate::orderBy('name')->get()->map(fn ($template) => ['value' => $template->id, 'label' => $template->name]),
            'forms' => Form::all()->map(fn ($form) => ['value' => $form->handle(), 'label' => $form->title()])->values(),
            'users' => User::all()->filter(fn ($user) => $user->can('view crm') || $user->isSuper())->map(fn ($user) => ['value' => $user->id(), 'label' => $user->name()])->values(),
            'runs' => $automation->exists ? $automation->runs()->with('contact')->latest('run_at')->latest('id')->limit(25)->get()->map(fn (AutomationRun $run) => [
                'id' => $run->id,
                'contact' => $run->contact?->name(),
                'contact_url' => $run->contact ? cp_route('radpack-crm.contacts.show', $run->contact) : null,
                'step' => $run->step + 1,
                'status' => $run->status,
                'result' => $run->result,
                'run_at' => $run->run_at?->toIso8601String(),
            ]) : [],
            'submitUrl' => $automation->exists ? cp_route('radpack-crm.automations.update', $automation) : cp_route('radpack-crm.automations.store'),
            'submitMethod' => $automation->exists ? 'patch' : 'post',
            'destroyUrl' => $automation->exists ? cp_route('radpack-crm.automations.destroy', $automation) : null,
            'cancelUrl' => cp_route('radpack-crm.automations.index'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'active' => ['boolean'],
            'trigger' => ['required', Rule::in(array_keys(CrmEvent::names()))],
            'trigger_options' => ['nullable', 'array'],
            'trigger_options.tag' => ['nullable', 'string', 'max:100'],
            'trigger_options.status' => ['nullable', 'string', 'max:50'],
            'trigger_options.form' => ['nullable', 'string', 'max:100'],
            'match' => ['required', Rule::in(['all', 'any'])],
            'conditions' => ['array', 'max:25'],
            'conditions.*.field' => ['required', Rule::in(array_keys(Segment::fields()))],
            'conditions.*.operator' => ['required', 'string', 'max:30'],
            'conditions.*.value' => ['nullable'],
            'conditions.*.key' => ['nullable', 'regex:/^[A-Za-z0-9_]+$/'],
            'actions' => ['required', 'array', 'min:1', 'max:20'],
            'actions.*.type' => ['required', Rule::in(array_keys(Actions::types()))],
            'actions.*.delay' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'actions.*.delay_unit' => ['nullable', Rule::in(['minutes', 'hours', 'days'])],
            'actions.*.template_id' => ['required_if:actions.*.type,send_email', 'nullable', 'integer'],
            'actions.*.url' => ['required_if:actions.*.type,webhook', 'nullable', 'url:https,http'],
            'actions.*.to' => ['required_if:actions.*.type,notify', 'nullable', 'string', 'max:1000'],
            'actions.*.status' => ['required_if:actions.*.type,set_status', 'nullable', 'string', 'max:50'],
            'actions.*.tags' => ['required_if:actions.*.type,add_tag,remove_tag', 'nullable'],
        ]);

        $data['trigger_options'] = array_filter((array) ($data['trigger_options'] ?? []));
        // Keep every action's settings (validated keys above, plus free text like titles and messages).
        $data['actions'] = array_map(
            fn ($action) => array_intersect_key($action, array_flip(['type', 'delay', 'delay_unit', 'tags', 'status', 'template_id', 'title', 'task_type', 'priority', 'due_in_days', 'assign_to', 'body', 'to', 'subject', 'message', 'url', 'phone_field', 'text'])),
            $request->input('actions'),
        );

        return $data;
    }
}
