![Alt text](https://github.com/markocupic/markocupic/blob/main/logo.png "logo")

# SAC Login (OpenId Connect Login client für Contao)

Diese Erweiterung für das [Contao CMS](https://contao.org) ermöglicht die Implementierung 
des Single Sign-On Logins des [Schweizerischen Alpen Clubs (SAC)](https://www.sac-cas.ch).

SAC Mitglieder der Sektion können sich mit ihrer Mitgliedsnummer und ihrem Passwort, welches sie auf der Webseite des [SAC Zentralverbandes](https://www.sac-cas.ch) verwalten, im Front- sowie im Backend anmelden.

| Backend | Frontend |
|-|-|
| ![SAC Login](docs/img/screenshot_backend_readme.png) | ![SAC Login](docs/img/screenshot_remote_login_form_readme.png) |

## Abhängigkeiten
Die Erweiterung basiert auf:
- [markocupic/sac-event-tool_bundle](https://github.com/markocupic/sac-event-tool-bundle) 
- [thephpleague/oauth2-client](https://github.com/thephpleague/oauth2-client)
- [codefog/contao-haste](https://github.com/codefog/contao-haste)

## Konfiguration
Vor der Inbetriebnahme muss die App konfiguriert werden. Erstellen Sie dazu einen neuen Abschnitt in config/config.yml.

```
markocupic_sac_sso_login:
  oidc:
    # required
    client_id: '###'
    client_secret: '###'
    enable_backend_sso: true
    auth_endpoint_frontend: '###'
    auth_endpoint_backend: '###'
    # defaults
    url_authorize: 'https://ids01.sac-cas.ch:443/oauth2/authorize'
    url_access_token: 'https://ids01.sac-cas.ch:443/oauth2/token'
    resource_owner_details: 'https://ids01.sac-cas.ch:443/oauth2/userinfo'
    url_logout: 'https://ids01.sac-cas.ch/oidc/logout'

    # optional frontend user settings
    add_to_frontend_user_groups:
      # - 9 # Standard Mitgliedergruppe
    autocreate_frontend_user: false
    allow_frontend_login_to_sac_members_only: true
    allow_frontend_login_to_predefined_section_members_only: true
    allowed_frontend_sac_section_ids:
      # - 4250 # Stammsektion
      # - 4251 # OG Surental
      # - 4252 # OG Napf
      # - 4253 # OG Hochdorf
      # - 4254 # OG Rigi

    # optional backend user settings
    autocreate_backend_user: false
    allow_backend_login_to_sac_members_only: true
    allow_backend_login_to_predefined_section_members_only: true
    allowed_backend_sac_section_ids:
      # - 4250 # Stammsektion
      # - 4251 # OG Surental
      # - 4252 # OG Napf
      # - 4253 # OG Hochdorf
      # - 4254 # OG Rigi

```


