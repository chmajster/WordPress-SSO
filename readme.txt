=== Plan WordPress SSO ===
Contributors: chmajster
Tags: sso, authentication, plan
Requires at least: 6.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bezpieczne SSO z WordPress do aplikacji Plan przy użyciu podpisanego tokenu HMAC-SHA256.

== Description ==

Plan WordPress SSO pozwala używać WordPress jako dostawcy tożsamości dla aplikacji Plan.

Plugin:
* korzysta z istniejącej sesji i standardowego logowania WordPress,
* mapuje role WordPress na role Plan,
* tworzy krótko żyjący payload SSO,
* podpisuje Base64URL(JSON) algorytmem HMAC-SHA256,
* przekierowuje wyłącznie do skonfigurowanego adresu aplikacji Plan,
* śledzi do 5 poprzednich adresów e-mail użytkownika,
* nie przekazuje haseł ani cookies WordPress do Plan.

== Installation ==

1. Spakuj katalog pluginu jako `plan-wordpress-sso.zip`.
2. W WordPress przejdź do Wtyczki -> Dodaj wtyczkę -> Wyślij wtyczkę na serwer.
3. Wskaż `plan-wordpress-sso.zip`, zainstaluj i aktywuj plugin.
4. Przejdź do Ustawienia -> Plan WordPress SSO.
5. Ustaw URL Plan, owner e-mail oraz wspólny sekret wygenerowany przez Plan.
6. Nadaj użytkownikom jedną z ról Plan albo użyj standardowej roli `administrator`.

== Frequently Asked Questions ==

= Czy plugin przesyła hasło WordPress do Plan? =

Nie. Do Plan trafia wyłącznie podpisany payload zawierający owner e-mail, e-mail użytkownika, historię poprzednich e-maili, rolę, timestamp i nonce.

= Jaki jest adres rozpoczęcia SSO? =

`https://wordpress.example.org/?plan_sso=1`

= Jakiego shortcode użyć? =

`[plan_sso_button]`

Można zmienić etykietę:

`[plan_sso_button label="Przejdź do aplikacji"]`

= Jakie role są obsługiwane? =

* `administrator` -> `admin`
* `plan_leader` -> `leader`
* `plan_deputy` -> `deputy`
* `plan_volunteer` -> `volunteer`

= Czy administrator WordPress zawsze może wejść do Plan? =

Tylko jeśli jego bieżący e-mail jest identyczny z owner e-mailem skonfigurowanym w ustawieniach pluginu.

== Changelog ==

= 1.0.0 =
* Pierwsza kompletna wersja samodzielnego pluginu Plan WordPress SSO.
