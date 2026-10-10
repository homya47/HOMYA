@props(['locale'])
@inject('channels', 'App\Services\ContactChannels')
<section class="purchase-help" aria-labelledby="purchase-help-heading">
    <div><h2 id="purchase-help-heading">{{ __('contact.purchase_help_heading') }}</h2><p>{{ __('contact.advice_intro') }}</p></div>
    <div class="purchase-help-actions"><a class="button-secondary" href="{{ route('information', ['locale' => $locale, 'page' => 'conseiller']) }}">{{ __('pages.entries.conseiller.title') }}</a>@if($channels->phone())<x-whatsapp-chat :floating="false" />@else<a class="button-primary" href="{{ route('information', ['locale' => $locale, 'page' => 'contact']) }}">{{ __('contact.advice_contact') }} <span aria-hidden="true">→</span></a>@endif</div>
</section>
