# SAC Login (OpenId Connect Login client für Contao)

Diese Erweiterung ermöglicht die Implementierung des Single Sign-On Logins des Schweizerischen Alpen Clubs (SAC) für Contao CMS.

![SAC Login](src/Resources/public/img/screenshot_backend_readme.png)

![SAC Login](src/Resources/public/img/screenshot_remote_login_form_readme.png)


Die Erweiterung basiert auf markocupic/sac-event-tool_bundle und thephpleague/oauth2-client. 
Vor der Inbetriebnahme müssen mehrere Parameter in der config/parameters.yml getätigt werden.

```
parameters:
  # some other settings


markocupic_sac_sso_login:
  oidc:
    # required
    client_id: '*****'
    client_secret:  '*****'
    # defaults
    url_authorize: 'https://ids01.sac-cas.ch:443/oauth2/authorize'
    url_access_token: 'https://ids01.sac-cas.ch:443/oauth2/token'
    resource_owner_details: 'https://ids01.sac-cas.ch:443/oauth2/userinfo'
    redirect_uri_frontend: 'https://sac-pilatus.ch/ssoauth/frontend'
    redirect_uri_backend: 'https://sac-pilatus.ch/ssoauth/backend'
    url_logout: 'https://ids01.sac-cas.ch/oidc/logout'
    enable_backend_sso: true
    # optional
    add_to_frontend_user_groups:
      - 9 # SAC Sektion Pilatus Standard Mitgliedergruppe
    allowed_sac_section_ids:
      - 4250 # Stammsektion
      - 4251 # OG Surental
      - 4252 # OG Napf
      - 4253 # OG Hochdorf
      - 4254 # OG Rigi



```
