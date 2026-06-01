# Terminarz Ajax PHP

Prosty terminarz internetowy z logowaniem, CRUD wpisów i Ajaxem bez przeładowywania strony.

## Funkcje

- logowanie do panelu przez Ajax
- domyślne dane: `admin` / `admin`
- wygląd prostego szkolnego kalendarza
- dodawanie, edycja i kasowanie wpisów
- wpisy z datą startu, końca i kategorią
- opcjonalne przypomnienie e-mail z poziomu pola formularza
- zapis w SQLite, bez dodatkowej bazy MySQL

## Uruchomienie

1. Upewnij się, że masz PHP z obsługą SQLite i serwerem wbudowanym.
2. Uruchom w katalogu projektu:

```bash
php -S localhost:8000
```

3. Otwórz przeglądarkę pod adresem `http://localhost:8000`.

## Przypomnienia e-mail

Opcjonalny skrypt `reminder.php` sprawdza wpisy, których czas przypomnienia już nadszedł, i próbuje wysłać wiadomość przez `mail()`.

Możesz uruchamiać go cyklicznie z crona, np. co minutę.

## Dane startowe

Po pierwszym uruchomieniu tworzy się konto:

- użytkownik: `admin`
- hasło: `admin`

oraz domyślne kategorie:

- Służbowe
- Prywatne
- Pilne
- Inne
