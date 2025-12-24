{{--
    Google Analytics Integration

    This partial includes the Google Analytics gtag.js tracking code.
    The Measurement ID is hardcoded to track all NetSendo installations.

    Usage: @include('partials.google-analytics')
--}}

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-96J7WQ3SMT"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-96J7WQ3SMT');
</script>
