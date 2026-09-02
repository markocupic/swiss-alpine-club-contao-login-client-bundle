![Alt text](https://github.com/markocupic/markocupic/blob/main/logo.png "logo")

# SAC Login (OAuth2 client für Contao)

Diese Erweiterung für das [Contao CMS](https://contao.org) ermöglicht die Implementierung
des Single Sign-On Logins des [Schweizerischen Alpen Clubs (SAC)](https://www.sac-cas.ch).

Siehe auch [OAUTH-Dokumentation](https://github.com/hitobito/hitobito/blob/master/doc/developer/people/oauth.md#openid-connect-oidc)

SAC Mitglieder der Sektion können sich mit ihrer Mitgliedsnummer und ihrem Passwort, welches sie auf der Webseite des [SAC Zentralverbandes](https://www.sac-cas.ch) verwalten, im Front- sowie im Backend anmelden.

| SAC Login Button                                                                                          | Login Formular Schweizerischer Alpenclub           |
|-----------------------------------------------------------------------------------------------------------|----------------------------------------------------|
| ![SAC Login](docs/img/frontend_login.png)                                                                 | ![SAC/CAS Portal](docs/img/login_form_sac_cas.png) |
| Bei Klick auf den Login Button erfolgt die Weiterleitug zum Login Formular des Schweizerischen Alpenclubs | Login Formular Schweizerischer Alpenclub           |

## Dependencies

Die Erweiterung besitzt folgende Abhängigkeiten:

- [contao/contao](https://github.com/contao/contao)
- [markocupic/sac-event-tool-bundle](https://github.com/markocupic/sac-event-tool-bundle)
- [thephpleague/oauth2-client](https://github.com/thephpleague/oauth2-client)
- [codefog/contao-haste](https://github.com/codefog/contao-haste)

## Hilfe/HowTo

[The PHP League OAuth2 client](https://oauth2-client.thephpleague.com/usage/)

## Konfiguration

Vor der Inbetriebnahme muss die App konfiguriert werden. Erstellen Sie dazu einen neuen Abschnitt in config/config.yaml.

```
sac_oauth2_client:
  backend:
    disable_contao_login: true ### Default to false. See the note below.
  oidc:
    # required
    client_id: '### Get your client id form SAC Schweiz ###'
    client_secret: '### Get your client secret form SAC Schweiz ###'
    enable_backend_sso: true

    # defaults
    debug_mode: false
    client_auth_endpoint_frontend_route: 'sac_login_redirect_frontend'
    client_auth_endpoint_backend_route: 'sac_login_redirect_backend'
    debug_mode: false # Log resource owners details (Contao backend log)
    auth_provider_endpoint_authorize: 'https://sac-cas.puzzle.ch/oauth/authorize'
    auth_provider_endpoint_token: 'https://sac-cas.puzzle.ch/oauth/token'
    auth_provider_endpoint_userinfo: 'https://sac-cas.puzzle.ch/de/oauth/profile'
    auth_provider_endpoint_discovery: 'https://sac-cas.puzzle.ch/.well-known/openid-configuration'
    pkce_method: 'S256' # Proof Key for Code Exchange (RFC 7636). 'S256' (default), 'plain' oder 'none'
    oauth_scopes: [ 'openid', 'with_roles', 'user_groups' ] # Default. Siehe Hinweis unten.
    auth_provider_endpoint_logout: 'https://ids01.sac-cas.ch/oidc/logout'

    # optional frontend user settings
    add_to_frontend_user_groups:
      - 9 # Standard Mitgliedergruppe
    auto_create_frontend_user: false
    allow_frontend_login_to_sac_members_only: true
    allow_frontend_login_to_predefined_section_members_only: true
    reactivate_disabled_frontend_user_on_login: false # Achtung: reaktiviert das Konto dauerhaft. Siehe Hinweis unten.
    allowed_frontend_roles:
        - 'Group::SektionsMitglieder::Ehrenmitglied'
        - 'Group::SektionsMitglieder::MitgliedZusatzsektion'
        - 'Group::SektionsMitglieder::Mitglied'
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
    allowed_backend_roles:
        - 'Group::SektionsMitglieder::Ehrenmitglied'
        - 'Group::SektionsMitglieder::MitgliedZusatzsektion'
        - 'Group::SektionsMitglieder::Mitglied'
    allowed_backend_sac_section_ids:
      - 4250 # Stammsektion
      - 4251 # OG Surental
      - 4252 # OG Napf
      - 4253 # OG Hochdorf
      - 4254 # OG Rigi

```

### Hinweis zu `oidc.reactivate_disabled_frontend_user_on_login`

Die Option hiess früher `allow_frontend_login_if_contao_account_is_disabled` und
war damit zu freundlich benannt: sie lässt ein deaktiviertes Mitglied nicht nur
herein, sie setzt `tl_member.disable` **dauerhaft** auf `false`. Ein Mitglied,
das im Backend bewusst deaktiviert wurde, ist nach seinem nächsten SSO-Login
wieder aktiv.

Das Backend-Gegenstück `allow_backend_login_if_contao_account_is_disabled`
verhält sich anders: es lässt den Benutzer herein, ohne `tl_user.disable` zu
verändern. Die beiden Optionen sind bewusst nicht mehr symmetrisch benannt.

Wer den alten Schlüssel gesetzt hat, muss ihn in `config/config.yaml`
umbenennen — sonst bricht der Container-Aufbau mit „Unrecognized option" ab.

### Upgrade-Hinweis: `tl_sac_login_session` entfällt

Der `id_token`, der beim Logout als `id_token_hint` an den Identity Provider
geht, liegt neu direkt in der Session statt in einer eigenen Tabelle. Damit
entfallen die Tabelle `tl_sac_login_session`, ihr DCA und der tägliche
Cron-Job, der abgelaufene Datensätze aufgeräumt hat.

Beim nächsten Datenbank-Update bietet Contao die verwaiste Tabelle zum
Löschen an. Wer zum Zeitpunkt des Updates eingeloggt ist, wird beim Logout
lokal abgemeldet, aber nicht mehr beim Identity Provider – ein einmaliger
Effekt, der sich mit dem nächsten Login erledigt.

### Hinweis zu `oidc.oauth_scopes`

Erlaubte Werte sind die Scopes des Hitobito-Providers und werden gegen das Enum
`OAuthScope` validiert:
`email`, `name`, `with_roles`, `openid`, `api`, `events`, `groups`, `people`,
`invoices`, `mailing_lists`, `user_groups`.

Ein Tippfehler führt bereits beim Cache-Aufbau zu einem Fehler, der die
zulässigen Werte auflistet.

### Hinweis zu `oidc.pkce_method`

Der Client sendet standardmässig einen PKCE-Code-Challenge nach RFC 7636
(`S256`). Der zugehörige Code-Verifier wird in der Session abgelegt und beim
Abholen des Access Tokens als `code_verifier` mitgeschickt.

Ob der Identity Provider PKCE unterstützt, steht im Discovery-Dokument unter
`code_challenge_methods_supported`. Sollte der Provider mit einem Fehler
antworten, kann PKCE mit `pkce_method: 'none'` abgeschaltet werden.

### Hinweis zu `backend.disable_contao_login`

Ist die Option aktiviert, wird das Contao-Login-Formular nicht nur ausgeblendet,
sondern serverseitig abgewiesen: POST-Requests mit `FORM_SUBMIT=tl_login` im
Backend-Scope beantwortet die Erweiterung mit `403 Forbidden`
(`DisableContaoBackendLoginListener`).

Damit ist eine Anmeldung im Backend **ausschliesslich** über den SAC-SSO-Login
möglich. Fällt der Identity Provider aus, kommt niemand mehr ins Backend – der
Ausweg ist, die Option in `config/config.yaml` wieder auf `false` zu setzen und
den Cache zu leeren.
