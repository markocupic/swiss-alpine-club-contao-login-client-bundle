![Alt text](https://github.com/markocupic/markocupic/blob/main/logo.png "logo")

# SAC Login (OAuth2 client für Contao)

Diese Erweiterung für das [Contao CMS](https://contao.org) ermöglicht die Implementierung 
des Single Sign-On Logins des [Schweizerischen Alpen Clubs (SAC)](https://www.sac-cas.ch).

SAC Mitglieder der Sektion können sich mit ihrer Mitgliedsnummer und ihrem Passwort, welches sie auf der Webseite des [SAC Zentralverbandes](https://www.sac-cas.ch) verwalten, im Front- sowie im Backend anmelden.

| SAC Login Button | Login Formular Schweizerischer Alpenclub |
|-|-|
| ![SAC Login](docs/img/screenshot_backend_readme.png) | ![SAC Login](docs/img/screenshot_remote_login_form_readme.png) |
| Bei Klick auf den Login Button erfolgt die Weiterleitug zum Login Formular des Schweizerischen Alpenclubs | Login Formular Schweizerischer Alpenclub |

## Dependencies
Die Erweiterung besitzt folgende Abhängigkeiten:
- [contao/contao](https://github.com/contao/contao)
- [markocupic/sac-event-tool-bundle](https://github.com/markocupic/sac-event-tool-bundle) 
- [thephpleague/oauth2-client](https://github.com/thephpleague/oauth2-client)
- [juststeveking/uri-builder](https://github.com/juststeveking/uri-builder)

## Hilfe/HowTo
[The PHP League Oauth2 client](https://oauth2-client.thephpleague.com/usage/)

## Konfiguration
Vor der Inbetriebnahme muss die App konfiguriert werden. Erstellen Sie dazu einen neuen Abschnitt in config/config.yaml.

```
sac_oauth2_client:
  backend:
    disable_contao_login: true ### Default to false
  oidc:
    # required
    client_id: '### Get your client id form SAC Schweiz ###'
    client_secret: '### Get your client secret form SAC Schweiz ###'
    enable_backend_sso: true
    
    # defaults
    client_auth_endpoint_frontend_route: 'swiss_alpine_club_sso_login_frontend'
    client_auth_endpoint_backend_route: 'swiss_alpine_club_sso_login_backend'
    debug_mode: false # Log resource owners details (Contao backend log)
    auth_provider_endpoint_authorize: 'https://ids01.sac-cas.ch:443/oauth2/authorize'
    auth_provider_endpoint_token: 'https://ids01.sac-cas.ch:443/oauth2/token'
    auth_provider_endpoint_userinfo: 'https://ids01.sac-cas.ch:443/oauth2/userinfo'
    auth_provider_endpoint_logout: 'https://ids01.sac-cas.ch/oidc/logout'

    # optional frontend user settings
    add_to_frontend_user_groups:
      - 9 # Standard Mitgliedergruppe
    auto_create_frontend_user: false
    allow_frontend_login_to_sac_members_only: true
    allow_frontend_login_to_predefined_section_members_only: true
    allow_frontend_login_if_contao_account_is_disabled: false # Do not allow login if contao member account is disabled or login is set to false
    allowed_frontend_sac_section_ids:
      - 4250 # Stammsektion
      - 4251 # OG Surental
      - 4252 # OG Napf
      - 4253 # OG Hochdorf
      - 4254 # OG Rigi

    # optional backend user settings
    auto_create_backend_user: false
    allow_backend_login_to_sac_members_only: true
    allow_backend_login_to_predefined_section_members_only: true
    allow_backend_login_if_contao_account_is_disabled: false # Do not allow login if contao user account is disabled
    allowed_backend_sac_section_ids:
      - 4250 # Stammsektion
      - 4251 # OG Surental
      - 4252 # OG Napf
      - 4253 # OG Hochdorf
      - 4254 # OG Rigi

```

## PreInteractiveLoginEvent
Der **PreInteractiveLoginEvent** wird getriggert bevor der Contao User Provider den Frontend oder Backend User lädt. 
Mit einem **Event Listener** oder **Event Subscriber** lassen sich so vor dem Contao Login Vorgang unter anderem Manipulationen am User Account vornehmen. 

