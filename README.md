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
    disable_contao_login: true ### Standardmässig false. Siehe Hinweise unten.
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
    enforce_frontend_two_factor: false # Contao-2FA nach dem SSO-Login verlangen. Siehe Hinweis unten.
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
    allow_backend_login_if_contao_account_is_disabled: false # Verhindere das Login bei daktiviertem Nutzerkonto.
    enforce_backend_two_factor: false # Contao-2FA nach dem SSO-Login verlangen. Siehe Hinweis unten.
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

Die Option ermöglich das Einloggen eines vorher deaktiviertes Mitglieds und setzt
`tl_member.disable` **dauerhaft** auf `false`. Ein Mitglied,
das im Backend bewusst deaktiviert wurde, ist nach seinem nächsten SSO-Login
wieder aktiv.

Das Backend-Gegenstück `allow_backend_login_if_contao_account_is_disabled`
verhält sich anders: es lässt den Benutzer herein, ohne `tl_user.disable` zu
verändern. Die beiden Optionen sind bewusst nicht mehr symmetrisch benannt.

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

### Hinweis zu `oidc.enforce_frontend_two_factor` und `oidc.enforce_backend_two_factor`

Standardmässig überspringt der SSO-Login Contaos Zwei-Faktor-Authentifizierung.
Der Authenticator setzt dafür das Attribut `FLAG_2FA_COMPLETE` auf dem Token und
meldet dem 2FA-Bundle damit, der zweite Faktor sei bereits erbracht. Die
Überlegung dahinter: Der Identity Provider ist der starke Faktor, und ein
Mitglied, das im Contao-Profil 2FA einschaltet, soll nicht plötzlich zwei
unabhängige zweite Faktoren pflegen müssen.

Wer das anders sehen will, schaltet es pro Scope ein. Dann bleibt das Attribut
ungesetzt, der `AuthenticationTokenListener` des 2FA-Bundles tauscht den Token
gegen einen `TwoFactorToken`, und Contaos `AuthenticationSuccessHandler` leitet
auf die Abfrage des zweiten Faktors um.

Drei Dinge sind dabei zu beachten:

1. Die Optionen **erzwingen keine Einrichtung**. Sie berücksichtigen lediglich,
   was das Mitglied selbst unter `useTwoFactor` gesetzt hat. Wer nie ein
   TOTP-Geheimnis in Contao hinterlegt hat, merkt von der Umstellung nichts.
2. Die **Backup-Codes liegen in Contao**, nicht beim Identity Provider. Wer sein
   Gerät verliert, kommt nur darüber wieder hinein.
   Ein Konto kann `useTwoFactor` tragen, ohne je eingerichtet worden zu sein — das
   Feld ist im Backend umschaltbar, das TOTP-Geheimnis (`secret`, `doNotShow`)
   nicht. Contao fängt diese Kombination nicht ab und stirbt mit einem `TypeError`
   in `Base32::encodeUpperUnpadded()`. Der Authenticator prüft deshalb, ob ein
   `secret` vorhanden ist, überspringt den zweiten Faktor sonst und schreibt eine
   Warnung ins Log.
3. Der Ablauf weicht bewusst von Contaos Standard ab. Contaos
   `AuthenticationSuccessHandler` leitet im 2FA-Zweig auf `$request->getUri()` um
   — bei einem SSO-Login also zurück auf die Callback-Route, deren `code` der
   Identity Provider bereits eingelöst hat. Der zweite Durchlauf scheitert dann
   mit „ungültiger State". Deshalb übernimmt `onAuthenticationSuccess()` diesen
   Fall selbst: Zielpfad merken (`TargetPathTrait`), auf den Zielpfad umleiten,
   und weil dieser Request keinen `code` trägt, hält sich der Authenticator
   heraus und der `TwoFactorAccessListener` fragt den Code ab.

### Zweiter Faktor im Frontend

Contaos eigenes Login-Modul rendert die Code-Abfrage selbst; das Modul dieser
Erweiterung musste es nachziehen. `Security::getUser()` liefert bei einem
`TwoFactorToken` bereits das Mitglied dahinter — ohne Sonderbehandlung hätte der
`SacOauthFrontendLoginController` also den „Du bist angemeldet"-Kasten gezeigt
und der Code wäre nirgends einzugeben gewesen.

Der Controller prüft deshalb zuerst `IS_AUTHENTICATED_2FA_IN_PROGRESS`, feuert
`TwoFactorAuthenticationEvents::FORM` (damit Provider, die einen Code
verschicken, das tun können) und rendert ein Formular mit dem Feld `verify`,
das per POST mit `FORM_SUBMIT=tl_login` an die aktuelle Seite geht — genau das,
worauf Contaos `ContaoLoginAuthenticator` hört. Ein Passwortfeld gibt es nicht.

Solange der zweite Faktor aussteht, schiebt Contaos `TwoFactorFrontendListener`
das Mitglied bei **jedem** Request zurück auf den gemerkten Zielpfad der Firewall.
Zeigt dieser auf eine Seite, die ihre eigene Query-String-Position weiterschreibt —
etwa ein Checkout, der von `?action=login` auf `?action=register` wechselt —,
leiten die beiden endlos aufeinander zu: Die Seite schiebt weiter, der Listener
zieht zurück. Der Authenticator parkt das Mitglied deshalb auf einer Seite, die
stehen bleibt: `twoFactorJumpTo` der Startseite, sonst die Startseite selbst.
Der eigentlich gewünschte Zielpfad wandert getrennt davon in der Session mit
(`HitobitoAuthenticator::SESSION_KEY_TWO_FACTOR_TARGET_PATH`) und landet im
`_target_path` des Formulars, greift also nach dem Code.

Damit das greift, muss das Mitglied nach dem SSO-Login auf einer Seite landen,
auf der dieses Modul steht. Der `TwoFactorFrontendListener` leitet auf den
gemerkten Zielpfad um; ist das eine geschützte Seite ohne Login-Modul, wirft er
eine `InsufficientAuthenticationException` und Contao geht auf die
401-Fehlerseite. Deren Weiterleitungsziel sollte also die Seite mit dem
SSO-Login-Modul sein.

### Zusammenspiel mit `backend.disable_contao_login`

Contaos Formular für die Code-Eingabe (`be_login_two_factor.html5`) schickt
denselben `FORM_SUBMIT=tl_login` wie das Passwortformular und wird vom selben
Authenticator verarbeitet. Ein pauschales Blockieren würde die Eingabe des
zweiten Faktors unmöglich machen.

Die naheliegende Bedingung — „liegt ein `TwoFactorToken` im Token-Storage" —
lässt sich an dieser Stelle nicht auswerten: Der `DisableContaoBackendLoginListener`
läuft mit Priorität 9 vor der Firewall, der Token-Storage ist dort noch leer.
Contaos eigener `ContaoLoginAuthenticator` hat dasselbe Problem und verschiebt
die Prüfung von `supports()` nach `authenticate()`.

Der Listener erkennt die Code-Eingabe deshalb an den Feldern des Requests: Sie
trägt `verify` und — anders als das Login-Formular — überhaupt kein
`password`-Feld. Das schwächt die Sperre nicht ab: Ohne Passwortfeld gibt es
nichts, womit sich ein Passwort-Login durchführen liesse (Symfony weist leere
`PasswordCredentials` ab), und in Contaos 2FA-Zweig landet nur, wer tatsächlich
einen offenen `TwoFactorToken` hat. Alles andere bekommt weiterhin `403`.

Zweitens muss das Formular überhaupt sichtbar bleiben. Der
`ParseBackendTemplateListener` entfernt bei aktivem `disable_contao_login` das
`<form class="tl_login_form">` aus der Seite — und Contao rendert die
Code-Abfrage mit `be_login_two_factor`, was auf dieselbe `be_login`-Bedingung
passt. Der Listener steigt deshalb aus, sobald ein `TwoFactorToken` im
Token-Storage liegt. An dieser Stelle im Request ist der Token verfügbar, anders
als im `DisableContaoBackendLoginListener`. Nebeneffekt und so gewollt: Auf der
Seite mit der Code-Abfrage erscheint auch kein SSO-Button mehr, der die
angefangene Anmeldung nur von vorn beginnen liesse.

### Hinweis zu `backend.disable_contao_login`

Ist die Option aktiviert, wird das Contao-Login-Formular nicht nur ausgeblendet,
sondern serverseitig abgewiesen: POST-Requests mit `FORM_SUBMIT=tl_login` im
Backend-Scope beantwortet die Erweiterung mit `403 Forbidden`
(`DisableContaoBackendLoginListener`).

Damit ist eine Anmeldung im Backend **ausschliesslich** über den SAC-SSO-Login
möglich. Fällt der Identity Provider aus, kommt niemand mehr ins Backend – der
Ausweg ist, die Option in `config/config.yaml` wieder auf `false` zu setzen und
den Cache zu leeren.
