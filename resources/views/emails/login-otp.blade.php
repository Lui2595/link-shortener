<x-mail::message>
# Your login code

Use this one-time code to access LPshortener. It expires in **10 minutes**.

# {{ $code }}

If you did not request this code, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
