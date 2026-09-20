<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('authentication.mail_subject') }}</title></head>
<body>
    <h1>{{ __('authentication.mail_subject') }}</h1>
    <p>{{ __('authentication.mail_intro') }}</p>
    <p><a href="{{ $resetUrl }}">{{ __('authentication.reset') }}</a></p>
    <p>{{ __('authentication.mail_ignore') }}</p>
</body>
</html>
