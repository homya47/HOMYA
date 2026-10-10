@inject('legalInformation', 'App\Services\LegalInformation')
@if($mediator = $legalInformation->mediator())
    @unless($mediator['membership_active'])<p role="note">{{ __('legal.mediator_membership_pending') }}</p>@endunless
    <dl class="company-details mediator-details">
        <div><dt>{{ __($mediator['membership_active'] ? 'legal.mediator_name' : 'legal.mediator_selected') }}</dt><dd>{{ $mediator['name'] }}</dd></div>
        <div><dt>{{ __('legal.mediator_address') }}</dt><dd>{{ $mediator['address'] }}</dd></div>
        <div><dt>{{ __('legal.mediator_website') }}</dt><dd><a href="{{ $mediator['website'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{ $mediator['website'] }} <span aria-hidden="true">↗</span></a></dd></div>
        @if(isset($mediator['email']))<div><dt>{{ __('legal.company_labels.email') }}</dt><dd><a href="mailto:{{ $mediator['email'] }}">{{ $mediator['email'] }}</a></dd></div>@endif
        @if(isset($mediator['phone']))<div><dt>{{ __('legal.company_labels.phone') }}</dt><dd><a href="tel:{{ $mediator['phone'] }}">{{ $mediator['phone'] }}</a></dd></div>@endif
    </dl>
@else
    <p>{{ __('legal.mediator_pending') }}</p>
@endif
