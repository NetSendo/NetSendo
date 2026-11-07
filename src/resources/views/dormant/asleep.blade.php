@extends('dormant.layout')

@section('title', __('dormant.asleep.title', [], $locale))

@section('content')
    <h1>{{ __('dormant.asleep.title', [], $locale) }}</h1>
    <p>{{ __('dormant.asleep.body', [], $locale) }}</p>
    <p>{{ __('dormant.asleep.owner', [], $locale) }}</p>
    <p class="note">{{ __('dormant.asleep.unsubscribe', [], $locale) }}</p>
@endsection
