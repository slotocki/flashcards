# 🧪 Instrukcja testowania MemoRise

## 🌐 Dostęp do aplikacji
**URL:** http://localhost:8080/login

---

## 👥 Konta testowe

| Email | Hasło | Rola |
|-------|-------|------|
| jan.kowalski@example.com | Password123 | **Student** |
| anna.nowak@example.com | Password123 | **Nauczyciel** |
| admin@memorise.pl | Password123 | **Admin** |

---

## 📋 Scenariusze testowe

### 1️⃣ Test jako STUDENT

#### Krok 1: Logowanie i dashboard
1. Otwórz http://localhost:8080/login
2. Zaloguj się jako **jan.kowalski@example.com** / **Password123**
3. Zobaczysz dashboard z klasą **"Klasa 4a - niemiecki"**

#### Krok 2: Dołączenie do nowej klasy kodem
1. Kliknij przycisk **"Dołącz do klasy"** (lub przejdź na http://localhost:8080/join)
2. Wpisz kod: **R477RCX4**
3. Kliknij "Dołącz do klasy"
4. Powinieneś zobaczyć komunikat sukcesu i przekierowanie na dashboard
5. Teraz na dashboardzie będą **2 klasy**:
   - Klasa 4a - niemiecki 🇩🇪
   - Angielski B1 🇬🇧

#### Krok 3: Nauka fiszek
1. Na dashboardzie kliknij na klasę **"Klasa 4a - niemiecki"**
2. Kliknij na zestaw **"Die Tiere"**
3. Kliknij **"Rozpocznij naukę"**
4. Zobaczysz fiszkę (np. "der Hund")
5. Kliknij kartę lub naciśnij **Spację**, aby odwrócić
6. Zobaczysz odpowiedź: "pies"
7. Kliknij **"Wiem"** (→) lub **"Nie wiem"** (←)
8. Nauka zapisuje progres - karta się zmienia
9. **Skróty klawiszowe:**
   - **Spacja** - odwróć kartę
   - **← (lewa strzałka)** - nie wiem
   - **→ (prawa strzałka)** - wiem

#### Krok 4: Zobacz progres
1. Kliknij **"Twój progres"** w menu (http://localhost:8080/progress)
2. Zobaczysz statystyki:
   - Opanowane karty
   - Uczę się
   - Nowe
3. Dla każdego decku zobaczysz pasek progresu

---

### 2️⃣ Test jako NAUCZYCIEL

#### Krok 1: Logowanie
1. Wyloguj się (kliknij "Wyloguj")
2. Zaloguj się jako **anna.nowak@example.com** / **Password123**

#### Krok 2: Panel nauczyciela
1. Kliknij **"Panel nauczyciela"** w menu (http://localhost:8080/teacher)
2. Zobaczysz swoje klasy:
   - Klasa 4a - niemiecki (kod: **ABC12345**)
   - Angielski B1 (kod: **R477RCX4**)

#### Krok 3: Tworzenie nowej klasy
1. Kliknij **"+ Utwórz klasę"**
2. Wypełnij formularz:
   - Nazwa: np. "Hiszpański A2"
   - Opis: np. "Kurs dla początkujących"
   - Język: **Hiszpański 🇪🇸**
3. Kliknij **"Utwórz"**
4. Zobaczysz komunikat z **kodem dołączenia** (zapisz go!)
5. Nowa klasa pojawi się na liście

#### Krok 4: Dodawanie zestawu fiszek
1. Kliknij na dowolną klasę z listy
2. Będziesz w zakładce **"Zestawy"**
3. Kliknij **"+ Dodaj zestaw"**
4. Wypełnij:
   - Tytuł: np. "Słownictwo - jedzenie"
   - Opis: opcjonalnie
   - Poziom: **Początkujący / Średni / Zaawansowany**
5. Kliknij **"Dodaj"**

#### Krok 5: Dodawanie fiszek
1. Po utworzeniu zestawu kliknij **"Zarządzaj fiszkami"**
2. Kliknij **"+ Dodaj fiszkę"**
3. Wypełnij:
   - Przód: np. "el pan"
   - Tył: np. "chleb"
4. Kliknij **"Dodaj"**
5. Dodaj więcej fiszek (repeat 2-4)

#### Krok 6: Zobacz uczniów w klasie
1. Kliknij zakładkę **"Uczniowie"**
2. Zobaczysz listę studentów zapisanych do klasy
3. Dla "Klasa 4a - niemiecki" zobaczysz: **Jan Kowalski**

#### Krok 7: Zadania
1. Kliknij zakładkę **"Zadania"**
2. Zobaczysz zadanie testowe z terminem
3. *Funkcja tworzenia zadań w przygotowaniu*

---

### 3️⃣ Test jako ADMIN

#### Krok 1: Logowanie
1. Zaloguj się jako **admin@memorise.pl** / **Password123**
2. *Panel admina w przygotowaniu - obecnie admin ma dostęp do wszystkich funkcji*

---

## 🧪 Testy API (curl/PowerShell)

### Login
```powershell
$body = @{email="jan.kowalski@example.com"; password="Password123"} | ConvertTo-Json
Invoke-RestMethod -Uri "http://localhost:8080/api/auth/login" -Method POST -ContentType "application/json" -Body $body -SessionVariable session
```

### Lista klas
```powershell
Invoke-RestMethod -Uri "http://localhost:8080/api/classes" -Method GET -WebSession $session
```

### Dołącz do klasy
```powershell
$joinData = @{joinCode="R477RCX4"} | ConvertTo-Json
Invoke-RestMethod -Uri "http://localhost:8080/api/classes/join" -Method POST -ContentType "application/json" -Body $joinData -WebSession $session
```

### Lista decków klasy
```powershell
Invoke-RestMethod -Uri "http://localhost:8080/api/classes/1/decks" -Method GET -WebSession $session
```

### Następna karta do nauki
```powershell
Invoke-RestMethod -Uri "http://localhost:8080/api/study/next?deckId=1" -Method GET -WebSession $session
```

### Zapisz odpowiedź
```powershell
$answerData = @{cardId=1; answer="know"} | ConvertTo-Json
Invoke-RestMethod -Uri "http://localhost:8080/api/progress/answer" -Method POST -ContentType "application/json" -Body $answerData -WebSession $session
```

### Statystyki progresu
```powershell
Invoke-RestMethod -Uri "http://localhost:8080/api/progress/stats" -Method GET -WebSession $session
```

---

## 📊 Dane w bazie

### Klasy utworzone
1. **Klasa 4a - niemiecki** (kod: `ABC12345`)
   - Deck: "Die Tiere" (5 fiszek)
   - Student: Jan Kowalski
   
2. **Angielski B1** (kod: `R477RCX4`)
   - Deck: "Basic Verbs" (2 fiszki)
   - Brak studentów (dołącz jako Jan!)

### Fiszki w "Die Tiere"
- der Hund → pies
- die Katze → kot
- der Vogel → ptak
- das Pferd → koń
- die Kuh → krowa

---

## ⚠️ Znane problemy / TODO

- [ ] Usuwanie decków/kart
- [ ] Edycja decków/kart
- [ ] Tworzenie zadań (przycisk jest, ale funkcja w przygotowaniu)
- [ ] Statystyki postępów studentów dla nauczyciela
- [ ] Panel admina
- [ ] Obsługa zdjęć na fiszkach
- [ ] Filtrowanie/wyszukiwanie klas

---

## 🐛 Debugowanie

### Sprawdź czy kontenery działają
```powershell
docker ps
```

### Logi aplikacji PHP
```powershell
docker logs flashcards-php-1
```

### Logi bazy danych
```powershell
docker logs flashcards-db-1
```

### Połącz się z bazą
```powershell
docker exec -it flashcards-db-1 psql -U docker -d db
```

### Sprawdź użytkowników w bazie
```sql
SELECT id, email, role FROM users;
```

### Sprawdź klasy
```sql
SELECT id, name, join_code, teacher_id FROM classes;
```

---

## 🎯 Szybki start (co przetestować w pierwszej kolejności)

1. ✅ **Zaloguj się jako student** (jan.kowalski@example.com)
2. ✅ **Dołącz do klasy kodem** R477RCX4
3. ✅ **Rozpocznij naukę** fiszek z "Die Tiere"
4. ✅ **Zobacz progres** po nauce
5. ✅ **Zaloguj się jako nauczyciel** (anna.nowak@example.com)
6. ✅ **Utwórz nową klasę** i zapisz kod
7. ✅ **Dodaj zestaw i fiszki**
8. ✅ **Wróć jako student** i dołącz do nowej klasy

---

**Miłej zabawy z testowaniem! 🚀**
