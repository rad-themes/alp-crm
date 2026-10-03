@include('alp-crm::portal._styles')

<section class="rp-section">
    <div class="rp-card rp-scroll">
        <table class="rp-table">
            <thead>
                <tr>
                    <th>{{ __('File') }}</th>
                    <th class="rp-hide-sm">{{ __('Added') }}</th>
                    <th class="rp-num">{{ __('Size') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($files as $file)
                    <tr>
                        <td><strong>{{ $file->name }}</strong></td>
                        <td class="rp-hide-sm">{{ $file->created_at?->isoFormat('ll') }}</td>
                        <td class="rp-num rp-muted">{{ $file->humanSize() }}</td>
                        <td><div class="rp-actions"><a class="rp-link" href="{{ route('statamic.alp-crm.portal.file', $file) }}">{{ __('Download') }}</a></div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
