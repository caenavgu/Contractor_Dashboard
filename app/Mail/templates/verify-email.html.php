<!doctype html>
<html>
  <body style="font-family:Arial,sans-serif;line-height:1.5">
    <h2 style="margin:0 0 12px">{{app_name}}</h2>
    <p>Hi {{user_name}},</p>
    <p>Please verify your email to activate your account. This link expires in {{expires_in}}.</p>
    <p style="margin:16px 0">
      <a href="{{verify_link}}" style="display:inline-block;padding:10px 16px;text-decoration:none;border-radius:6px;border:1px solid #333">
        {{cta_text}}
      </a>
    </p>
    {{footer_html}}
  </body>
</html>
