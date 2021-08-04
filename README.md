![Alt text](https://github.com/markocupic/markocupic/blob/main/logo.png "logo")

# SAC Login (OpenId Connect Login client für Contao)

Diese Erweiterung für das [Contao CMS](https://contao.org) ermöglicht die Implementierung 
des Single Sign-On Logins des [Schweizerischen Alpen Clubs (SAC)](https://www.sac-cas.ch).


SAC Mitglieder der Sektion können sich mit ihrer Mitgliedsnummer und ihrem Passwort, welches sie auf der Webseite des [SAC Zentralverbandes](https://www.sac-cas.ch) verwalten, im Front- sowie im Backend anmelden.


![SAC Login](docs/img/screenshot_backend_readme.png)

![SAC Login](docs/img/screenshot_remote_login_form_readme.png)


Die Erweiterung basiert auf markocupic/sac-event-tool_bundle und thephpleague/oauth2-client. 
Vor der Inbetriebnahme müssen mehrere Parameter in der config/parameters.yml getätigt werden.

```
parameters:
  # some other settings


markocupic_sac_sso_login:
  oidc:
    # required
    client_id: '*******'
    client_secret:  '**********'
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
    autocreate_frontend_user: false
    allow_frontend_login_to_sac_members_only: true
    allow_frontend_login_to_predefined_section_members_only: true
    allowed_frontend_sac_section_ids:
      - 4250 # Stammsektion
      - 4251 # OG Surental
      - 4252 # OG Napf
      - 4253 # OG Hochdorf
      - 4254 # OG Rigi
    autocreate_backend_user: false
    allow_backend_login_to_sac_members_only: true
    allow_backend_login_to_predefined_section_members_only: true
    allowed_backend_sac_section_ids:
      - 4250 # Stammsektion
      - 4251 # OG Surental
      - 4252 # OG Napf
      - 4253 # OG Hochdorf
      - 4254 # OG Rigi

```
