{{--
    Plain table-based HTML on purpose: this has to read correctly in Gmail on
    an Android phone, which is the only client that matters here, and which
    strips anything cleverer.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign-in code</title>
</head>
<body style="margin:0; padding:24px; background:#f4f4f6; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#1c1b1f;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; margin:0 auto; background:#ffffff; border-radius:14px; overflow:hidden;">
        <tr>
            <td style="background:#5c1626; padding:20px 24px;">
                <div style="color:#ffffff; font-size:18px; font-weight:700;">Invoice Generator</div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                @if ($name)
                    <p style="margin:0 0 12px; font-size:15px;">Hello {{ $name }},</p>
                @endif

                <p style="margin:0 0 18px; font-size:15px; line-height:1.5;">
                    Use this code to sign in:
                </p>

                <div style="font-size:34px; font-weight:800; letter-spacing:10px; text-align:center; padding:16px; background:#faf1f3; border-radius:10px; color:#5c1626;">
                    {{ $code }}
                </div>

                <p style="margin:18px 0 0; font-size:13px; color:#605b62; line-height:1.5;">
                    It expires in {{ $expiresInMinutes }} minutes and can be used once.
                    If you didn't ask to sign in, ignore this email — nobody can
                    get in without the code.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
