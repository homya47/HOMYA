@inject('legalInformation', 'App\Services\LegalInformation')
@if($address = $legalInformation->returnAddress())
    <dl class="company-details return-details">
        <div><dt>{{ __('legal.return_address') }}</dt><dd>HOMYA<br>{{ $address }}</dd></div>
    </dl>
@endif
