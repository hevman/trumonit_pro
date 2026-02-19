# HEVMAN CORE
## Dokumentacja Strategiczna, Architektoniczna i Biznesowa (PL)

**Wersja:** 1.1  
**Data:** 2026-02-12  
**Domena główna:** `https://hevman.com`  
**Produkty:** `TruMonit` + `TruCookie`

---

## 0. Cel dokumentu

Ten dokument porządkuje kierunek rozwoju firmy **Hevman Core** na poziomie:
- strategii biznesowej,
- architektury produktów,
- bezpieczeństwa i operacji,
- planu dostarczania wartości w 12 miesięcy.

To jest dokument operacyjny: ma ułatwiać decyzje o priorytetach i ograniczać overengineering.

---

## 1. Nazwa i struktura marki

### 1.1 Nazwa rekomendowana
- **Hevman Core**

Uzasadnienie:
- „Core” komunikuje fundament, stabilność i warstwę infrastrukturalną.
- Nazwa jest neutralna branżowo i skaluje się na wiele produktów.
- Pasuje do pozycjonowania: security + compliance.

### 1.2 Alternatywy (rebranding)
- Hevman Systems
- Hevman Technologies
- Hevman Infrastructure
- Hevman Security
- Hevman Labs

### 1.3 Architektura marki
- marka nadrzędna: **Hevman Core**,
- marki produktowe: **TruMonit**, **TruCookie**,
- zasada: osobne produkty, wspólny standard jakości i zaufania.

---

## 2. Wizja, misja, pozycjonowanie

### 2.1 Wizja
Budowa infrastruktury zaufania dla nowoczesnego internetu.

### 2.2 Misja
Tworzenie lekkich, technicznych i skalowalnych narzędzi SaaS w obszarze bezpieczeństwa i compliance.

### 2.3 Pozycjonowanie
Hevman Core to firma produktowa dostarczająca infrastrukturę B2B:
- bezpieczeństwo aplikacji webowych,
- monitoring i wykrywanie zagrożeń,
- zgodność regulacyjna (GDPR/ePrivacy).

### 2.4 Zasada strategiczna
- najpierw przychód, potem integracja,
- najpierw walidacja, potem rozbudowa,
- brak rozbudowy „na zapas” bez popytu od klientów.

---

## 3. Struktura produktowa

## 3.1 TruMonit (Web-based Intrusion Detection System)

### Cel produktu
Monitorowanie i szybkie wykrywanie ryzyk dla stron i aplikacji webowych:
- dostępność (uptime),
- nieautoryzowane zmiany treści,
- anomalie odpowiedzi i przekierowań,
- sygnały bezpieczeństwa (nagłówki/TLS/DNS),
- incydenty i eskalacja.

### Problem klienta
- brak szybkiej detekcji awarii i defacementu,
- zbyt dużo fałszywych alertów,
- brak audytowalnego śladu incydentów.

### Grupa docelowa
- SMB i SaaS,
- software house’y i agencje,
- organizacje z wymaganiem raportowalności i kontroli operacyjnej.

### Model przychodowy
- abonament miesięczny/roczny,
- limity per plan (monitory, retencja, kanały),
- dodatki: dłuższa retencja, dodatkowe regiony/proby prywatne, eksporty compliance.

---

## 3.2 TruCookie (Consent Management Platform)

### Cel produktu
Umożliwienie zgodności z:
- GDPR,
- ePrivacy,
- wymogami dot. cookies i zgód marketingowych.

### Zakres funkcjonalny
- baner zgody i preferencji,
- logowanie dowodów zgody,
- eksport zgód i historii zmian,
- geolokalizacja i polityki regionalne,
- API i SDK do integracji.

### Grupa docelowa
- serwisy contentowe,
- e-commerce,
- agencje i integratorzy,
- SMB z ruchem międzynarodowym.

### Model przychodowy
- abonament zależny od ruchu,
- plany agencyjne i multi-site,
- white-label dla partnerów.

---

## 4. Architektura firmy (strategiczna)

### 4.1 Zasada nadrzędna
Produkty są niezależne technicznie, ale spójne wizerunkowo.

### 4.2 Struktura domenowa (docelowa)
- `hevman.com` – strona firmowa i zaufanie marki,
- `https://trumonit.pro` – produkt security,
- `https://trucookie.pro` – produkt compliance.

### 4.3 Niezależność systemów
- osobne repozytoria,
- osobne bazy danych,
- osobne deploymenty,
- brak twardych zależności runtime.

### 4.4 Integracje międzyproduktowe (etap późniejszy)
- wspólne SSO,
- wspólny billing,
- portal klienta (centralny workspace).

---

## 5. Architektura techniczna (ogólna)

### 5.1 Filary
- API-first,
- izolacja tenantów,
- audyt operacji i zmian,
- polityki retencji,
- backup + odtwarzanie,
- obserwowalność i alerting.

### 5.2 Standard bezpieczeństwa (minimum)
- HTTPS wszędzie,
- rate limiting i throttling,
- silna walidacja wejścia,
- SSRF hardening dla każdego outbound fetch,
- least privilege,
- rotacja sekretów i kluczy,
- logika „secure by default”.

### 5.3 Standard jakości
- testy automatyczne (unit/feature/integracyjne),
- migracje i rollback DB,
- changelog operacyjny,
- runbooki incydentowe.

---

## 6. Segmentacja klientów i ICP

### 6.1 Segmenty
- **SMB:** szybkie wdrożenie i niski koszt wejścia,
- **Mid-market:** większa skala i integracje,
- **Enterprise:** governance, compliance, izolacja danych.

### 6.2 ICP dla TruMonit
- firma ma krytyczny ruch web/API,
- brak lub słaba dojrzałość monitoringu bezpieczeństwa,
- potrzeba szybkich alertów i historii incydentów.

### 6.3 ICP dla TruCookie
- firma operuje w UE lub przetwarza dane obywateli UE,
- ma strony/e-commerce z cookies i tagami marketingowymi,
- wymaga audytowalnego dowodu zgód.

---

## 7. Oferta i pakiety (hipoteza komercyjna)

## 7.1 TruMonit
- **Lite:** podstawowy monitoring, podstawowe alerty, krótsza retencja.
- **Pro:** większe limity, integracje, zaawansowane filtry/raporty operacyjne.
- **Enterprise:** SSO, zaawansowany RBAC, polityki compliance, prywatne proby.

- **Cennik:** `https://trumonit.pro/pricing`
## 7.2 TruCookie
- **Starter:** 1 domena, podstawowy baner i logi.
- **Growth:** większy ruch, API, polityki regionalne.
- **Agency/Enterprise:** multi-site, white-label, zaawansowane eksporty i DPA.

- **Cennik:** `https://trucookie.pro/pricing`
## 7.3 Dodatki
- retencja rozszerzona,
- prywatne proby/regionalność,
- raportowanie formalne (moduł enterprise),
- usługi wdrożeniowe i wsparcie premium.

## 7.4 Linki produktowe (pakiety i cennik)
- TruMonit: `https://trumonit.pro` oraz `https://trumonit.pro/pricing` 
- TruCookie: `https://trucookie.pro` oraz `https://trucookie.pro/pricing`
---

## 8. Go-to-market (GTM)

### 8.1 Kanały pozyskania
- SEO i treści eksperckie (security/compliance),
- outbound do software house i e-commerce,
- partnerstwa agencyjne,
- demo techniczne i trial.

### 8.2 Lejek sprzedażowy
1. Ruch organiczny / referral  
2. Trial lub demo  
3. Aktywacja (pierwszy monitor / pierwszy baner)  
4. Konwersja płatna  
5. Rozszerzenie planu i retencja

### 8.3 Warunek wzrostu
Priorytetem jest szybka aktywacja i krótkie time-to-value, nie rozbudowa funkcji „na zapas”.

---

## 9. Plan rozwoju (12 miesięcy)

### Faza 1: Walidacja (0–3 mies.)
- stabilne MVP,
- pierwsi użytkownicy i ręczny onboarding,
- feedback loop i poprawki jakościowe.

### Faza 2: Stabilizacja (3–6 mies.)
- automatyzacja płatności,
- komplet podstawowej dokumentacji technicznej,
- case studies,
- publikacja `hevman.com`.

### Faza 3: Skalowanie (6–12 mies.)
- 20+ płacących klientów,
- SEO i kanały partnerskie,
- integracje OAuth/SSO (jeśli uzasadnione),
- redukcja kosztu obsługi klienta.

---

## 10. KPI i metryki sukcesu

### 10.1 Biznes
- MRR,
- liczba płacących klientów,
- churn netto i brutto,
- ARPA/ARPU.

### 10.2 Produkt
- activation rate (pierwsza wartość <24h),
- time-to-value,
- retention 30/90 dni,
- procent aktywnych tenantów tygodniowo.

### 10.3 Operacje
- dostępność usług,
- p95 czasu reakcji API,
- scan lag p95 / queue wait p95 (TruMonit),
- RTO/RPO z testów odtwarzania.

---

## 11. Uproszczony biznesplan

### 11.1 Główne koszty
- infrastruktura cloud/VPS,
- provider e-mail,
- operator płatności (np. Stripe),
- domeny i certyfikaty,
- czas developmentu i wsparcia.

### 11.2 Cele finansowe (hipotezy)
- **Rok 1:** 50 klientów × 20 EUR = 1000 EUR MRR
- **Rok 2:** 300 klientów × 25 EUR = 7500 EUR MRR

### 11.3 Krytyczne założenia
- szybka aktywacja klienta,
- niska awaryjność i niski poziom false positives,
- transparentny pricing i czytelna wartość planów.

---

## 12. Strona firmowa `hevman.com`

### Cel strony
Budowanie wiarygodności i zaufania technologicznego, nie agresywna sprzedaż.

### Struktura treści
1. Kim jesteśmy  
2. Co budujemy  
3. Produkty (TruMonit, TruCookie)  
4. Podejście do bezpieczeństwa  
5. Kontakt

### Styl komunikacji
- profesjonalny,
- techniczny,
- konkretny,
- bez marketingowej przesady.

---

## 13. Dokumentacja startowa (wymagana)

1. Regulamin (ToS)  
2. Polityka prywatności  
3. DPA (umowa powierzenia)  
4. Polityka bezpieczeństwa  
5. Procedura reagowania na incydenty  
6. Procedura backupu i odtwarzania  
7. Publiczna roadmapa produktowa

Dla enterprise dodatkowo:
- polityka retencji i usuwania danych,
- standardy audytu,
- opis kontroli dostępu i izolacji tenantów.

---

## 14. Rejestr ryzyk i mitigacje

### Ryzyka produktowe
- zbyt szeroki zakres MVP,
- niska konwersja trial -> paid,
- za długi onboarding.

**Mitigacje:** ograniczony scope, szybki feedback, iteracje co 1–2 tygodnie.

### Ryzyka techniczne
- wzrost kosztów infrastruktury,
- przeciążenie kolejek i skanowania,
- błędy bezpieczeństwa.

**Mitigacje:** limity per tenant, obserwowalność, testy, hardening i runbooki.

### Ryzyka prawne/compliance
- nieaktualne zapisy polityk,
- błędy w procesie zgód (TruCookie),
- brak spójności DPA.

**Mitigacje:** regularne przeglądy prawne, wersjonowanie dokumentów, audyt zmian.

---

## 15. Zasady decyzji produktowych

1. Funkcja trafia do roadmapy tylko jeśli:
   - podnosi przychód, retencję lub redukuje koszty operacyjne,
   - ma walidację w realnym problemie klienta.
2. Brak rozbudowy „bo konkurencja ma”.
3. Każda większa funkcja ma kryteria DoD:
   - wdrożona,
   - przetestowana,
   - udokumentowana,
   - operacyjnie monitorowana.

---

## 16. Plan działań 30/60/90 dni

### 0–30 dni
- domknięcie stabilności MVP TruMonit,
- publikacja pierwszej wersji `hevman.com`,
- przygotowanie dokumentów prawnych v1.

### 31–60 dni
- automatyzacja billing/check-out,
- uruchomienie trial + onboarding flow,
- pierwsze case study klienta.

### 61–90 dni
- iteracje planów cenowych,
- wzmocnienie kanałów acquisition,
- przygotowanie fundamentu enterprise (SSO/compliance backlog).

---

## 17. Podsumowanie strategiczne

Hevman Core powinien rozwijać się w modelu:
- **dwa niezależne produkty (TruMonit + TruCookie),**
- **jedna spójna marka i standard jakości,**
- **priorytet na przychód i walidację,**
- **skalowanie dopiero po potwierdzeniu PMF.**

To podejście minimalizuje ryzyko, utrzymuje tempo i pozwala budować trwałą firmę produktową.

---

## 18. Decyzja UX portfolio (TruMonit + TruCookie)

### 18.1 Decyzja
Nie robimy pełnego „kopiuj-wklej” layoutu między `TruMonit` i `TruCookie`.
Robimy **unifikację warstwową**:
- wspólna warstwa marki i komponentów,
- osobna warstwa workflow dla każdego produktu.

### 18.2 Co ma być wspólne (must)
- logo, nazewnictwo i tone of voice,
- typografia, spacing, radius, stany przycisków, formularzy i tabel,
- dark/light mode (ten sam mechanizm i semantyka),
- język, profile, auth shell, podstawowe komponenty feedbacku (success/warn/error).

### 18.3 Co zostaje różne (intencjonalnie)
- architektura nawigacji:
  - `TruMonit`: top-nav + operacyjne dashboardy i tabele incydentów,
  - `TruCookie`: sidebar + workflow site -> banner -> scan -> policy.
- akcent kolorystyczny produktu (security vs compliance),
- kolejność ekranów i CTA wynikające z ICP.

### 18.4 Kryterium wykonania
Za „spójne portfolio” uznajemy stan, gdy:
- użytkownik po zalogowaniu rozpoznaje ten sam standard UI w 3 sekundy,
- komponenty bazowe pochodzą z jednej biblioteki tokenów,
- dark/light zachowuje się identycznie w obu produktach,
- onboarding i główne taski nadal są zoptymalizowane pod konkretny produkt.

---

## 19. Analiza `TruCookie` (stan: 2026-02-12)

### 19.1 Co jest mocne
- pełny lejek produktu (landing/pricing/demo/free audit),
- funkcjonalny core TruCookie: banner, consent log, policy templates, scan/report, billing,
- plugin API i flow WordPress,
- działający build frontu oraz brak znanych CVE (`composer audit`, `npm audit`).

### 19.2 Luki do domknięcia przed skalą
- **Test quality gap:** test suite nie jest wiarygodnym gate produkcyjnym (failujące testy auth/session).
- **Coverage gap:** testy praktycznie nie obejmują krytycznego domain flow TruCookie (audit, banner config, consent telemetry, plugin API, billing).
- **Security hardening gap:** skanery URL (light/public scan jobs) wymagają centralnego SSRF guard; obecnie ochrona jest punktowa.
- **API governance gap:** plugin token model wymaga scopes/expiry/rotation policy i audytu operacji.
- **Ops/docs gap:** `backend/README.md` jest nadal generyczny (Laravel default), brakuje zwartej dokumentacji produktu i runbooku supportowego.

### 19.3 Priorytet operacyjny (najbliższe 30 dni)
1. Ustabilizować test harness + zielony CI gate dla krytycznych flow auth/session.
2. Dodać feature testy TruCookie dla: free audit, consent log, banner config API, plugin API, billing checkout/portal.
3. Wprowadzić wspólny SSRF policy service dla wszystkich outbound fetchy.
4. Wprowadzić plugin token governance (scope + expiry + rotate + revoke + audit).
5. Dopisać realny `backend/README.md` (architektura, run commands, debug, known limits).

### 19.4 Wniosek biznesowy
`TruCookie` jest dobrym kandydatem do dalszej sprzedaży SMB (MVP+),
ale przed mocniejszym skalowaniem wymaga domknięcia quality/security/ops foundation,
żeby ograniczyć ryzyko supportowe i reputacyjne.
