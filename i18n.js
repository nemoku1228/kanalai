/**
 * i18n.js — centralizuotas statinio teksto vertimo žodynas.
 * Naudojamas visuose marketplace puslapiuose (index, categories, product,
 * checkout, completed, track, account, info puslapiai).
 *
 * Kalbos: lt (lietuvių), en (English), ru (русский), lv (latviešu),
 *         et (eesti), fi (suomi)
 *
 * Naudojimas puslapyje:
 *   <script src="i18n.js"></script>
 *   ...
 *   t('cart')                 -> grąžina vertimą pagal currentLang()
 *   setLang('en')              -> pakeičia kalbą, atnaujina visus [data-i18n] elementus, perkrauna puslapį/produktus jei reikia
 *   applyI18n()                 -> pritaiko vertimus visiems elementams su data-i18n atributu
 */

/**
 * Grąžina tinkamą <img src> reikšmę nuotraukai — jei tai pilnas URL
 * (Allegro ar kitas išorinis šaltinis), naudoja jį tiesiai; jei tai
 * vietinis failo vardas, prideda uploads/products/ kelią.
 */
function resolveImageSrc(img) {
    if (!img) return '';
    if (/^https?:\/\//i.test(img)) return img;
    return 'uploads/products/' + img;
}

/**
 * Nuskaito viešus svetainės nustatymus (pavadinimą, hero tekstą) iš
 * get_site_settings.php ir pritaiko juos visiems elementams su
 * data-site-name / data-hero-eyebrow / data-hero-title atributais.
 * Naudoti visuose puslapiuose: <span data-site-name>market</span>
 */
async function applySiteSettings() {
    try {
        const res = await fetch('get_site_settings.php?lang=' + currentLangCode());
        const data = await res.json();
        if (!data.ok) return;

        document.querySelectorAll('[data-site-name]').forEach(el => { el.textContent = data.site_name; });
        document.querySelectorAll('[data-hero-eyebrow]').forEach(el => { el.textContent = data.hero_eyebrow; });
        document.querySelectorAll('[data-hero-title]').forEach(el => { el.textContent = data.hero_title; });
        document.title = document.title.replace(/market/gi, data.site_name);
    } catch (e) { /* tylus klaidos apdorojimas — palieka numatytąjį tekstą */ }
}

/**
 * Patikrina prisijungimo sesiją ir atnaujina "Mano paskyra" nuorodas
 * pagal statusą: prisijungus rodo "Mano paskyra" -> account.html,
 * neprisijungus rodo "Prisijungti / Registracija" -> account.html
 * (kur klientas tada matys prisijungimo formą).
 * Naudoja elementus su id="header-account-link"/"header-account-label"
 * ir id="topbar-account-link", jei jie egzistuoja puslapyje.
 */
async function checkAccountState() {
    try {
        const res = await fetch('session.php');
        const data = await res.json();
        const isLoggedIn = !!data.ok;

        const headerLabel = document.getElementById('header-account-label');
        const topbarLink = document.getElementById('topbar-account-link');

        if (headerLabel) {
            headerLabel.textContent = isLoggedIn ? t('my_account') : t('login_or_register');
            headerLabel.removeAttribute('data-i18n');
        }
        if (topbarLink) {
            topbarLink.textContent = isLoggedIn ? t('my_account') : t('login_or_register');
            topbarLink.removeAttribute('data-i18n');
        }
    } catch (e) { /* tylus klaidos apdorojimas — palieka numatytąjį "Mano paskyra" tekstą */ }
}

const I18N_LANGS = ['lt', 'en', 'ru', 'lv', 'et', 'fi'];

const I18N_FLAGS = {
    lt: 'lt',
    en: 'gb',
    ru: 'ru',
    lv: 'lv',
    et: 'ee',
    fi: 'fi',
};

const I18N_NAMES = {
    lt: 'Lietuvių',
    en: 'English',
    ru: 'Русский',
    lv: 'Latviešu',
    et: 'Eesti',
    fi: 'Suomi',
};

const I18N_DICT = {
    // ── Navigacija / bendri elementai ──────────────────────────────
    track_order:      { lt:'Sekti užsakymą', en:'Track order', ru:'Отследить заказ', lv:'Sekot pasūtījumam', et:'Jälgi tellimust', fi:'Seuraa tilausta' },
    view_in_account:  { lt:'Žiūrėti paskyroje', en:'View in account', ru:'Посмотреть в аккаунте', lv:'Skatīt kontā', et:'Vaata kontol', fi:'Katso tilillä' },
    my_account:       { lt:'Mano paskyra', en:'My account', ru:'Мой аккаунт', lv:'Mans konts', et:'Minu konto', fi:'Oma tili' },
    login_or_register:{ lt:'Prisijungti / Registracija', en:'Sign in / Register', ru:'Войти / Регистрация', lv:'Pieteikties / Reģistrēties', et:'Logi sisse / Registreeru', fi:'Kirjaudu / Rekisteröidy' },
    cart:             { lt:'Krepšelis', en:'Cart', ru:'Корзина', lv:'Grozs', et:'Ostukorv', fi:'Ostoskori' },
    search_placeholder:{ lt:'Ko ieškote šiandien?', en:'What are you looking for today?', ru:'Что вы ищете сегодня?', lv:'Ko jūs šodien meklējat?', et:'Mida te täna otsite?', fi:'Mitä etsit tänään?' },
    all_categories:   { lt:'Visos kategorijos', en:'All categories', ru:'Все категории', lv:'Visas kategorijas', et:'Kõik kategooriad', fi:'Kaikki kategoriat' },
    logout:           { lt:'Atsijungti', en:'Log out', ru:'Выйти', lv:'Iziet', et:'Logi välja', fi:'Kirjaudu ulos' },

    // ── Hero / pagrindinis ────────────────────────────────────────
    hero_title_1:     { lt:'Viskas, ko reikia,', en:'Everything you need,', ru:'Всё, что вам нужно,', lv:'Viss, kas nepieciešams,', et:'Kõik, mida vajate,', fi:'Kaikki tarvittava,' },
    hero_eyebrow:     { lt:'LIETUVOS EL. PARDUOTUVĖ', en:'LITHUANIAN ONLINE STORE', ru:'ЛИТОВСКИЙ ИНТЕРНЕТ-МАГАЗИН', lv:'LIETUVAS INTERNETA VEIKALS', et:'LEEDU E-POOD', fi:'LIETTUAN VERKKOKAUPPA' },
    free_delivery_from:{ lt:'Nuo 50€', en:'From €50', ru:'От 50€', lv:'No 50€', et:'Alates 50€', fi:'Alkaen 50€' },
    bank_transfer:    { lt:'Banko pavedimu', en:'Bank transfer', ru:'Банковским переводом', lv:'Bankas pārskaitījums', et:'Pangaülekanne', fi:'Tilisiirto' },
    returns_days:     { lt:'14 dienų', en:'14 days', ru:'14 дней', lv:'14 dienas', et:'14 päeva', fi:'14 päivää' },
    support_email:    { lt:'El. paštu', en:'By email', ru:'По эл. почте', lv:'E-pastā', et:'E-posti teel', fi:'Sähköpostitse' },
    hero_title_2:     { lt:'vienoje vietoje', en:'in one place', ru:'в одном месте', lv:'vienā vietā', et:'ühes kohas', fi:'yhdessä paikassa' },
    hero_sub:         { lt:'Elektronika, mada, namų prekės, automobiliai ir tūkstančiai kitų prekių vienoje parduotuvėje.', en:'Electronics, fashion, home goods, cars and thousands of other products in one store.', ru:'Электроника, мода, товары для дома, автомобили и тысячи других товаров в одном магазине.', lv:'Elektronika, moda, mājsaimniecības preces, automašīnas un tūkstošiem citu produktu vienā veikalā.', et:'Elektroonika, mood, kodukaubad, autod ja tuhanded teised tooted ühes poes.', fi:'Elektroniikkaa, muotia, kodintarvikkeita, autoja ja tuhansia muita tuotteita yhdessä kaupassa.' },
    view_catalog:     { lt:'Žiūrėti katalogą', en:'View catalog', ru:'Смотреть каталог', lv:'Skatīt katalogu', et:'Vaata kataloogi', fi:'Katso luettelo' },
    browse_by_category:{ lt:'Naršyti pagal kategoriją', en:'Browse by category', ru:'Просмотр по категориям', lv:'Pārlūkot pēc kategorijas', et:'Sirvi kategooria järgi', fi:'Selaa kategorian mukaan' },
    all_cats_link:    { lt:'Visos kategorijos →', en:'All categories →', ru:'Все категории →', lv:'Visas kategorijas →', et:'Kõik kategooriad →', fi:'Kaikki kategoriat →' },
    latest_products:  { lt:'Naujausios prekės', en:'Latest products', ru:'Новые товары', lv:'Jaunākie produkti', et:'Uusimad tooted', fi:'Uusimmat tuotteet' },

    // ── Pagrindinio puslapio sekcijos ─────────────────────────────
    super_offers:        { lt:'SUPER pasiūlymai', en:'SUPER deals', ru:'СУПЕР предложения', lv:'SUPER piedāvājumi', et:'SUPER pakkumised', fi:'SUPER tarjoukset' },
    super_offers_sub:    { lt:'Geriausios kainos šią savaitę', en:'Best prices this week', ru:'Лучшие цены недели', lv:'Labākās cenas šonedēļ', et:'Nädala parimad hinnad', fi:'Viikon parhaat hinnat' },
    top_picks:           { lt:'TOP pasirinkimai', en:'TOP picks', ru:'ТОП выбор', lv:'TOP izvēle', et:'TOP valikud', fi:'TOP valinnat' },
    top_picks_sub:       { lt:'Pirkėjų mėgstamiausios prekės', en:'Customer favourites', ru:'Любимые товары покупателей', lv:'Pircēju iecienītākās preces', et:'Klientide lemmikud', fi:'Asiakkaiden suosikit' },
    popular_now:         { lt:'Šiuo metu populiaru', en:'Popular right now', ru:'Сейчас популярно', lv:'Šobrīd populāri', et:'Hetkel populaarne', fi:'Juuri nyt suosittua' },
    popular_now_sub:     { lt:'Daugiausiai peržiūrų pastaruoju metu', en:'Most viewed recently', ru:'Чаще всего просматривают', lv:'Visvairāk skatītie', et:'Enim vaadatud', fi:'Katsotuimmat juuri nyt' },
    more_offers:         { lt:'Daugiau gerų pasiūlymų', en:'More great deals', ru:'Больше выгодных предложений', lv:'Vairāk labu piedāvājumu', et:'Veel häid pakkumisi', fi:'Lisää hyviä tarjouksia' },
    more_offers_sub:     { lt:'Puiki kaina ir kokybė', en:'Great price and quality', ru:'Отличная цена и качество', lv:'Lieliska cena un kvalitāte', et:'Hea hind ja kvaliteet', fi:'Erinomainen hinta ja laatu' },
    recommended_categories:{ lt:'Rekomenduojamos kategorijos', en:'Recommended categories', ru:'Рекомендуемые категории', lv:'Ieteicamās kategorijas', et:'Soovitatud kategooriad', fi:'Suositellut kategoriat' },
    inspired_by_viewed:  { lt:'Įkvėpta Jūsų peržiūrėtų produktų', en:'Inspired by your browsing', ru:'На основе просмотренных товаров', lv:'Iedvesmojoties no skatītā', et:'Sinu vaadatu põhjal', fi:'Selailusi perusteella' },
    inspired_by_viewed_sub:{ lt:'Panašu į tai, ką jau žiūrėjote', en:'Similar to what you viewed', ru:'Похоже на просмотренное вами', lv:'Līdzīgi jūsu skatītajam', et:'Sarnane vaadatuga', fi:'Samankaltaista kuin katsoit' },
    your_viewed:         { lt:'Jūsų peržiūrėtos prekės', en:'Recently viewed', ru:'Вы недавно смотрели', lv:'Jūsu skatītās preces', et:'Hiljuti vaadatud', fi:'Äskettäin katsotut' },
    see_all:             { lt:'Žiūrėti visus →', en:'See all →', ru:'Смотреть все →', lv:'Skatīt visus →', et:'Vaata kõiki →', fi:'Katso kaikki →' },
    clear_history:       { lt:'Išvalyti istoriją', en:'Clear history', ru:'Очистить историю', lv:'Notīrīt vēsturi', et:'Tühjenda ajalugu', fi:'Tyhjennä historia' },
    add_to_cart:      { lt:'Į krepšelį', en:'Add to cart', ru:'В корзину', lv:'Pievienot grozam', et:'Lisa ostukorvi', fi:'Lisää koriin' },
    buy_now:          { lt:'Pirkti dabar', en:'Buy now', ru:'Купить сейчас', lv:'Pirkt tagad', et:'Ostke kohe', fi:'Ostä nyt' },
    no_products:      { lt:'Prekių dar nėra', en:'No products yet', ru:'Товаров пока нет', lv:'Produktu vēl nav', et:'Tooteid veel ei ole', fi:'Tuotteita ei vielä ole' },

    // ── Trust strip ───────────────────────────────────────────────
    fast_delivery:    { lt:'Greitas pristatymas', en:'Fast delivery', ru:'Быстрая доставка', lv:'Ātra piegāde', et:'Kiire kohaletoimetamine', fi:'Nopea toimitus' },
    secure_payments:  { lt:'Saugūs mokėjimai', en:'Secure payments', ru:'Безопасные платежи', lv:'Droši maksājumi', et:'Turvalised maksed', fi:'Turvalliset maksut' },
    easy_returns:     { lt:'Lengvi grąžinimai', en:'Easy returns', ru:'Лёгкий возврат', lv:'Vienkārša atgriešana', et:'Lihtne tagastamine', fi:'Helpot palautukset' },
    customer_support: { lt:'Klientų aptarnavimas', en:'Customer support', ru:'Поддержка клиентов', lv:'Klientu atbalsts', et:'Klienditeenindus', fi:'Asiakaspalvelu' },

    // ── Krepšelis ─────────────────────────────────────────────────
    cart_empty:       { lt:'Krepšelis tuščias', en:'Cart is empty', ru:'Корзина пуста', lv:'Grozs ir tukšs', et:'Ostukorv on tühi', fi:'Ostoskori on tyhjä' },
    total:            { lt:'Viso:', en:'Total:', ru:'Итого:', lv:'Kopā:', et:'Kokku:', fi:'Yhteensä:' },
    checkout_btn:     { lt:'Pirkti', en:'Checkout', ru:'Оформить', lv:'Pirkt', et:'Vormista', fi:'Kassalle' },

    // ── Kategorijos puslapis ─────────────────────────────────────
    categories_title: { lt:'FILTRAI', en:'FILTERS', ru:'ФИЛЬТРЫ', lv:'FILTRI', et:'FILTRID', fi:'SUODATTIMET' },
    search_category:  { lt:'Ieškoti kategorijos...', en:'Search category...', ru:'Поиск категории...', lv:'Meklēt kategoriju...', et:'Otsi kategooriat...', fi:'Hae kategoriaa...' },
    search_products:  { lt:'Ieškoti prekių...', en:'Search products...', ru:'Поиск товаров...', lv:'Meklēt produktus...', et:'Otsi tooteid...', fi:'Hae tuotteita...' },
    all_products:     { lt:'Visos prekės', en:'All products', ru:'Все товары', lv:'Visi produkti', et:'Kõik tooted', fi:'Kaikki tuotteet' },
    not_found_in_cat: { lt:'Šioje kategorijoje prekių nerasta', en:'No products found in this category', ru:'Товары в этой категории не найдены', lv:'Šajā kategorijā produkti nav atrasti', et:'Selles kategoorias tooteid ei leitud', fi:'Tästä kategoriasta ei löytynyt tuotteita' },
    categories_btn:   { lt:'Kategorijos', en:'Categories', ru:'Категории', lv:'Kategorijas', et:'Kategooriad', fi:'Kategoriat' },

    // ── Produkto puslapis ─────────────────────────────────────────
    back_to_shop:     { lt:'Atgal į parduotuvę', en:'Back to shop', ru:'Назад в магазин', lv:'Atpakaļ uz veikalu', et:'Tagasi poodi', fi:'Takaisin kauppaan' },
    in_stock:         { lt:'Yra sandėlyje', en:'In stock', ru:'В наличии', lv:'Ir noliktavā', et:'Laos olemas', fi:'Varastossa' },
    low_stock:        { lt:'Liko nedaug', en:'Low stock', ru:'Осталось немного', lv:'Atlikušas nedaudz', et:'Vähe alles', fi:'Vähissä' },
    out_of_stock:     { lt:'Šiuo metu nėra sandėlyje', en:'Currently out of stock', ru:'Сейчас нет в наличии', lv:'Pašlaik nav noliktavā', et:'Praegu laos puudub', fi:'Ei juuri nyt varastossa' },
    out_of_stock_badge:{ lt:'Išparduota', en:'Out of stock', ru:'Нет в наличии', lv:'Nav pieejams', et:'Otsas', fi:'Loppu' },
    stock_limit_reached:{ lt:'Pasiektas maksimalus kiekis sandėlyje!', en:'Maximum available stock reached!', ru:'Достигнуто максимальное количество на складе!', lv:'Sasniegts maksimālais noliktavas daudzums!', et:'Maksimaalne laoseis on saavutatud!', fi:'Varaston enimmäismäärä saavutettu!' },
    units:            { lt:'vnt.', en:'units', ru:'шт.', lv:'gab.', et:'tk', fi:'kpl' },
    description:      { lt:'Aprašymas', en:'Description', ru:'Описание', lv:'Aprakts', et:'Kirjeldus', fi:'Kuvaus' },
    delivery_tab:     { lt:'Pristatymas', en:'Delivery', ru:'Доставка', lv:'Piegāde', et:'Kohaletoimetamine', fi:'Toimitus' },
    no_description:   { lt:'Aprašymo nėra.', en:'No description.', ru:'Описание отсутствует.', lv:'Aprakста nav.', et:'Kirjeldus puudub.', fi:'Ei kuvausta.' },
    ask_question_tab: { lt:'Klausimai', en:'Questions', ru:'Вопросы', lv:'Jautājumi', et:'Küsimused', fi:'Kysymykset' },
    product_code_label:{ lt:'Prekės kodas', en:'Product code', ru:'Код товара', lv:'Produkta kods', et:'Toote kood', fi:'Tuotekoodi' },
    ask_question_intro:{ lt:'Turite klausimų apie šią prekę? Užduokite klausimą — atsakysime el. paštu.', en:'Have questions about this product? Ask us — we will reply by email.', ru:'Есть вопросы об этом товаре? Спросите нас — ответим по электронной почте.', lv:'Ir jautājumi par šo produktu? Uzdodiet jautājumu — atbildēsim e-pastā.', et:'Küsimusi selle toote kohta? Küsi meilt — vastame e-postiga.', fi:'Onko sinulla kysyttävää tästä tuotteesta? Kysy meiltä — vastaamme sähköpostitse.' },
    your_question_placeholder:{ lt:'Jūsų klausimas...', en:'Your question...', ru:'Ваш вопрос...', lv:'Jūsu jautājums...', et:'Teie küsimus...', fi:'Kysymyksesi...' },
    submit_question_btn:{ lt:'Užduoti klausimą', en:'Submit question', ru:'Отправить вопрос', lv:'Uzdot jautājumu', et:'Esita küsimus', fi:'Lähetä kysymys' },
    question_sent_msg:{ lt:'Jūsų klausimas išsiųstas! Atsakysime el. paštu artimiausiu metu.', en:'Your question has been sent! We will reply by email shortly.', ru:'Ваш вопрос отправлен! Мы ответим по электронной почте в ближайшее время.', lv:'Jūsu jautājums nosūtīts! Atbildēsim e-pastā tuvākajā laikā.', et:'Teie küsimus on saadetud! Vastame e-postiga lähiajal.', fi:'Kysymyksesi on lähetetty! Vastaamme sähköpostitse pian.' },
    delivery_tab_text:{ lt:'Prekė siunčiama per 1-2 darbo dienas po užsakymo patvirtinimo. Pristatymo laikas priklauso nuo pasirinkto būdo (kurjeris, paštomatas, autobusų siunta).', en:'The item is shipped within 1-2 business days after order confirmation. Delivery time depends on the chosen method (courier, parcel locker, bus parcel).', ru:'Товар отправляется в течение 1-2 рабочих дней после подтверждения заказа. Срок доставки зависит от выбранного способа (курьер, постомат, автобусная доставка).', lv:'Prece tiek nosūtīta 1-2 darba dienu laikā pēc pasūtījuma apstiprinājuma. Piegādes laiks ir atkarīgs no izvēlētā veida (kurjers, pakomāts, autobusu sūtījums).', et:'Toode saadetakse 1-2 tööpäeva jooksul pärast tellimuse kinnitamist. Tarneaeg sõltub valitud viisist (kuller, pakiautomaat, bussisaadetis).', fi:'Tuote lähetetään 1-2 arkipäivän kuluessa tilausvahvistuksesta. Toimitusaika riippuu valitusta tavasta (kuriiri, pakettiautomaatti, linja-autolähetys).' },
    trust_delivery:   { lt:'Greitas pristatymas 2-4 darbo dienos', en:'Fast delivery 2-4 business days', ru:'Быстрая доставка 2-4 рабочих дня', lv:'Ātra piegāde 2-4 darba dienas', et:'Kiire kohaletoimetamine 2-4 tööpäeva', fi:'Nopea toimitus 2-4 arkipäivää' },
    trust_returns:    { lt:'14 dienų grąžinimo teisė', en:'14-day return policy', ru:'14 дней на возврат', lv:'14 dienu atgriešanas tiesības', et:'14 päeva tagastamisõigus', fi:'14 päivän palautusoikeus' },
    trust_payment:    { lt:'Saugus apmokėjimas', en:'Secure payment', ru:'Безопасная оплата', lv:'Drošs maksājums', et:'Turvaline makse', fi:'Turvallinen maksu' },
    not_found_product:{ lt:'Prekė nerasta.', en:'Product not found.', ru:'Товар не найден.', lv:'Produkts netika atrasts.', et:'Toodet ei leitud.', fi:'Tuotetta ei löytynyt.' },

    // ── Checkout ──────────────────────────────────────────────────
    step_cart:        { lt:'Krepšelis', en:'Cart', ru:'Корзина', lv:'Grozs', et:'Ostukorv', fi:'Ostoskori' },
    step_login:       { lt:'Prisijungimas', en:'Sign in', ru:'Вход', lv:'Pieteikšanās', et:'Sisselogimine', fi:'Kirjautuminen' },
    step_delivery:    { lt:'Pristatymas', en:'Delivery', ru:'Доставка', lv:'Piegāde', et:'Kohaletoimetamine', fi:'Toimitus' },
    continue_how:     { lt:'Kaip norite tęsti?', en:'How would you like to continue?', ru:'Как вы хотите продолжить?', lv:'Kā jūs vēlaties turpināt?', et:'Kuidas soovite jätkata?', fi:'Miten haluat jatkaa?' },
    login_to_account: { lt:'Prisijungti prie paskyros', en:'Sign in to account', ru:'Войти в аккаунт', lv:'Pieteikties kontā', et:'Logi kontosse', fi:'Kirjaudu tilille' },
    create_account:   { lt:'Susikurti paskyrą', en:'Create an account', ru:'Создать аккаунт', lv:'Izveidot kontu', et:'Loo konto', fi:'Luo tili' },
    guest_checkout:    { lt:'Pirkti be registracijos', en:'Checkout as guest', ru:'Купить без регистрации', lv:'Pirkt bez reģistrācijas', et:'Ostke registreerimata', fi:'Osta rekisteröitymättä' },
    back:             { lt:'Atgal', en:'Back', ru:'Назад', lv:'Atpakaļ', et:'Tagasi', fi:'Takaisin' },
    login_title:      { lt:'Prisijungimas', en:'Sign in', ru:'Вход', lv:'Pieteikšanās', et:'Sisselogimine', fi:'Kirjautuminen' },
    email_label:      { lt:'El. paštas', en:'Email', ru:'Эл. почта', lv:'E-pasts', et:'E-post', fi:'Sähköposti' },
    password_label:   { lt:'Slaptažodis', en:'Password', ru:'Пароль', lv:'Parole', et:'Parool', fi:'Salasana' },
    fill_all_fields:  { lt:'Užpildykite visus laukus!', en:'Please fill in all fields!', ru:'Заполните все поля!', lv:'Lūdzu, aizpildiet visus laukus!', et:'Palun täida kõik väljad!', fi:'Täytä kaikki kentät!' },
    invalid_email_format:{ lt:'Įveskite teisingą el. pašto adresą!', en:'Please enter a valid email address!', ru:'Введите правильный адрес электронной почты!', lv:'Lūdzu, ievadiet derīgu e-pasta adresi!', et:'Palun sisestage kehtiv e-posti aadress!', fi:'Anna kelvollinen sähköpostiosoite!' },
    invalid_phone_format:{ lt:'Įveskite teisingą telefono numerį!', en:'Please enter a valid phone number!', ru:'Введите правильный номер телефона!', lv:'Lūdzu, ievadiet derīgu tālruņa numuru!', et:'Palun sisestage kehtiv telefoninumber!', fi:'Anna kelvollinen puhelinnumero!' },

    // ── Pradžios puslapio dizainas (market-standalone) ────────────
    hero_badge:{ lt:'2,4 mln. prekių · pristatymas visoje Lietuvoje', en:'2.4M products · delivery across Lithuania', ru:'2,4 млн товаров · доставка по всей Литве', lv:'2,4 milj. preču · piegāde visā Lietuvā', et:'2,4 mln toodet · tarne üle Leedu', fi:'2,4 milj. tuotetta · toimitus koko Liettuaan' },
    hero_h1:{ lt:'Apsipirk protingiau su', en:'Shop smarter with', ru:'Покупай умнее с', lv:'Iepērcies gudrāk ar', et:'Osta nutikamalt', fi:'Osta fiksummin' },
    hero_sub2:{ lt:'Viena vieta elektronikai, madai, namams ir tūkstančiams prekių. Parenkame tai, ko tikrai reikia — greitai, saugiai ir už geriausią kainą.', en:'One place for electronics, fashion, home and thousands of products. We pick what you really need — fast, safe and at the best price.', ru:'Одно место для электроники, моды, дома и тысяч товаров. Подбираем то, что действительно нужно — быстро, безопасно и по лучшей цене.', lv:'Viena vieta elektronikai, modei, mājai un tūkstošiem preču. Atlasām to, kas patiešām vajadzīgs — ātri, droši un par labāko cenu.', et:'Üks koht elektroonikale, moele, kodule ja tuhandetele toodetele. Valime, mida tõesti vajad — kiiresti, turvaliselt ja parima hinnaga.', fi:'Yksi paikka elektroniikalle, muodille, kodille ja tuhansille tuotteille. Valitsemme sen, mitä todella tarvitset — nopeasti, turvallisesti ja parhaaseen hintaan.' },
    hero_cta_search:{ lt:'Pradėti paiešką', en:'Start searching', ru:'Начать поиск', lv:'Sākt meklēšanu', et:'Alusta otsingut', fi:'Aloita haku' },
    hero_cta_browse:{ lt:'Naršyti kategorijas', en:'Browse categories', ru:'Смотреть категории', lv:'Pārlūkot kategorijas', et:'Sirvi kategooriaid', fi:'Selaa kategorioita' },
    stat_sellers:{ lt:'pardavėjų', en:'sellers', ru:'продавцов', lv:'pārdevēju', et:'müüjat', fi:'myyjää' },
    stat_reviews:{ lt:'98k atsiliepimų', en:'98k reviews', ru:'98 тыс. отзывов', lv:'98k atsauksmju', et:'98k arvustust', fi:'98k arvostelua' },
    stat_delivery:{ lt:'pristatymas', en:'delivery', ru:'доставка', lv:'piegāde', et:'tarne', fi:'toimitus' },
    deal_tag:{ lt:'⚡ DIENOS PASIŪLYMAS', en:'⚡ DEAL OF THE DAY', ru:'⚡ ПРЕДЛОЖЕНИЕ ДНЯ', lv:'⚡ DIENAS PIEDĀVĀJUMS', et:'⚡ PÄEVA PAKKUMINE', fi:'⚡ PÄIVÄN TARJOUS' },
    deal_placeholder:{ lt:'Geriausias šios dienos pasiūlymas', en:'Best deal of the day', ru:'Лучшее предложение дня', lv:'Labākais dienas piedāvājums', et:'Päeva parim pakkumine', fi:'Päivän paras tarjous' },
    deal_ship_t:{ lt:'Pristatymas rytoj', en:'Delivery tomorrow', ru:'Доставка завтра', lv:'Piegāde rīt', et:'Tarne homme', fi:'Toimitus huomenna' },
    deal_ship_s:{ lt:'Nemokamai nuo 50 €', en:'Free from €50', ru:'Бесплатно от 50 €', lv:'Bez maksas no 50 €', et:'Tasuta alates 50 €', fi:'Ilmainen yli 50 €' },
    deal_price_t:{ lt:'Geriausia kaina', en:'Best price', ru:'Лучшая цена', lv:'Labākā cena', et:'Parim hind', fi:'Paras hinta' },
    deal_price_s:{ lt:'Atitinka rinkos kainą', en:'Matches market price', ru:'Соответствует рыночной цене', lv:'Atbilst tirgus cenai', et:'Vastab turuhinnale', fi:'Vastaa markkinahintaa' },
    deal_warr_t:{ lt:'24 mėn. garantija', en:'24-month warranty', ru:'Гарантия 24 мес.', lv:'24 mēn. garantija', et:'24 kuu garantii', fi:'24 kk takuu' },
    deal_warr_s:{ lt:'14 d. grąžinimas', en:'14-day returns', ru:'Возврат 14 дней', lv:'14 d. atgriešana', et:'14 päeva tagastus', fi:'14 pv palautus' },
    browse_cats_title:{ lt:'Naršyk kategorijas', en:'Browse categories', ru:'Категории', lv:'Pārlūko kategorijas', et:'Sirvi kategooriaid', fi:'Selaa kategorioita' },
    browse_cats_sub:{ lt:'100+ kategorijų, viskas vienoje vietoje', en:'100+ categories, all in one place', ru:'100+ категорий в одном месте', lv:'100+ kategorijas vienuviet', et:'100+ kategooriat ühes kohas', fi:'100+ kategoriaa yhdessä paikassa' },
    subcats_word:{ lt:'pakategorės', en:'subcategories', ru:'подкатегорий', lv:'apakškategorijas', et:'alamkategooriat', fi:'alakategoriaa' },
    explore_word:{ lt:'Atrasti →', en:'Explore →', ru:'Открыть →', lv:'Atklāt →', et:'Avasta →', fi:'Tutustu →' },
    flash_title:{ lt:'Žaibo pasiūlymai', en:'Flash deals', ru:'Молниеносные предложения', lv:'Zibakcijas', et:'Välkpakkumised', fi:'Salamatarjoukset' },
    flash_sub:{ lt:'Ribotas kiekis · kainos krenta kas valandą', en:'Limited stock · prices drop hourly', ru:'Ограниченное количество · цены падают каждый час', lv:'Ierobežots daudzums · cenas krīt katru stundu', et:'Piiratud kogus · hinnad langevad iga tund', fi:'Rajoitettu määrä · hinnat laskevat tunneittain' },
    flash_ends:{ lt:'Baigiasi po', en:'Ends in', ru:'Заканчивается через', lv:'Beidzas pēc', et:'Lõpeb', fi:'Päättyy' },
    ai_picked:{ lt:'AI PARINKTA TAU', en:'AI PICKED FOR YOU', ru:'ИИ ПОДОБРАЛ ВАМ', lv:'AI IZVĒLĒTS TEV', et:'AI VALITUD SULLE', fi:'AI VALITSI SINULLE' },
    see_everything:{ lt:'Žiūrėti viską →', en:'See all →', ru:'Смотреть всё →', lv:'Skatīt visu →', et:'Vaata kõike →', fi:'Katso kaikki →' },
    top100:{ lt:'Topas 100 →', en:'Top 100 →', ru:'Топ 100 →', lv:'Tops 100 →', et:'Top 100 →', fi:'Top 100 →' },
    feat_ship_t:{ lt:'Greitas pristatymas', en:'Fast delivery', ru:'Быстрая доставка', lv:'Ātra piegāde', et:'Kiire tarne', fi:'Nopea toimitus' },
    feat_ship_s:{ lt:'24 val. didžiuosiuose miestuose', en:'24h in major cities', ru:'24 ч в крупных городах', lv:'24 h lielajās pilsētās', et:'24 h suuremates linnades', fi:'24 h suurissa kaupungeissa' },
    feat_prot_t:{ lt:'Pirkėjo apsauga', en:'Buyer protection', ru:'Защита покупателя', lv:'Pircēja aizsardzība', et:'Ostja kaitse', fi:'Ostajan suoja' },
    feat_prot_s:{ lt:'Pinigai grąžinami 100%', en:'100% money back', ru:'Возврат денег 100%', lv:'100% naudas atmaksa', et:'100% raha tagasi', fi:'100% rahat takaisin' },
    feat_ret_t:{ lt:'Lengvi grąžinimai', en:'Easy returns', ru:'Лёгкий возврат', lv:'Vienkārša atgriešana', et:'Lihtne tagastus', fi:'Helpot palautukset' },
    feat_ret_s:{ lt:'14 dienų be priežasties', en:'14 days, no reason needed', ru:'14 дней без причины', lv:'14 dienas bez iemesla', et:'14 päeva põhjuseta', fi:'14 päivää ilman syytä' },
    feat_help_t:{ lt:'Pagalba 24/7', en:'Support 24/7', ru:'Поддержка 24/7', lv:'Atbalsts 24/7', et:'Tugi 24/7', fi:'Tuki 24/7' },
    feat_help_s:{ lt:'Realūs žmonės, ne botai', en:'Real people, not bots', ru:'Реальные люди, не боты', lv:'Īsti cilvēki, ne boti', et:'Päris inimesed, mitte robotid', fi:'Oikeita ihmisiä, ei botteja' },
    foot_buyers:{ lt:'Pirkėjams', en:'For buyers', ru:'Покупателям', lv:'Pircējiem', et:'Ostjatele', fi:'Ostajille' },
    foot_sellers:{ lt:'Pardavėjams', en:'For sellers', ru:'Продавцам', lv:'Pārdevējiem', et:'Müüjatele', fi:'Myyjille' },
    foot_how_buy:{ lt:'Kaip pirkti', en:'How to buy', ru:'Как покупать', lv:'Kā iepirkties', et:'Kuidas osta', fi:'Miten ostaa' },
    foot_warranties:{ lt:'Garantijos', en:'Warranties', ru:'Гарантии', lv:'Garantijas', et:'Garantiid', fi:'Takuut' },
    foot_start_selling:{ lt:'Pradėk pardavinėti', en:'Start selling', ru:'Начать продавать', lv:'Sākt pārdot', et:'Alusta müüki', fi:'Aloita myynti' },
    foot_pricing:{ lt:'Įkainiai', en:'Pricing', ru:'Тарифы', lv:'Cenas', et:'Hinnakiri', fi:'Hinnoittelu' },
    foot_seller_center:{ lt:'Pardavėjo centras', en:'Seller center', ru:'Центр продавца', lv:'Pārdevēja centrs', et:'Müüja keskus', fi:'Myyjäkeskus' },
    foot_ads:{ lt:'Reklama', en:'Advertising', ru:'Реклама', lv:'Reklāma', et:'Reklaam', fi:'Mainonta' },
    foot_career:{ lt:'Karjera', en:'Careers', ru:'Карьера', lv:'Karjera', et:'Karjäär', fi:'Ura' },
    foot_press:{ lt:'Spauda', en:'Press', ru:'Пресса', lv:'Prese', et:'Press', fi:'Lehdistö' },
    recommended_for_you:{ lt:'Rekomenduojama tau', en:'Recommended for you', ru:'Рекомендуем вам', lv:'Ieteicams tev', et:'Soovitatud sulle', fi:'Suositeltua sinulle' },
    trusted_brands:{ lt:'Patikimi prekės ženklai', en:'Trusted brands', ru:'Надёжные бренды', lv:'Uzticami zīmoli', et:'Usaldusväärsed brändid', fi:'Luotetut brändit' },
    all_brands:{ lt:'Visi ženklai →', en:'All brands →', ru:'Все бренды →', lv:'Visi zīmoli →', et:'Kõik brändid →', fi:'Kaikki brändit →' },
    bestseller_eyebrow:{ lt:'PERKAMIAUSIA', en:'BESTSELLING', ru:'ХИТЫ ПРОДАЖ', lv:'PĒRKAMĀKIE', et:'ENIMMÜÜDUD', fi:'MYYDYIMMÄT' },
    bestsellers_title:{ lt:'Bestseleriai šią savaitę', en:'Bestsellers this week', ru:'Бестселлеры недели', lv:'Šīs nedēļas bestselleri', et:'Selle nädala bestsellerid', fi:'Viikon bestsellerit' },
    foot_desc:{ lt:'Moderniausias Lietuvos turgus internete. Milijonai prekių, tūkstančiai pardavėjų, vienas patikimas adresas.', en:'Lithuania\u2019s most modern online marketplace. Millions of products, thousands of sellers, one trusted address.', ru:'Самый современный онлайн-рынок Литвы. Миллионы товаров, тысячи продавцов, один надёжный адрес.', lv:'Modernākais Lietuvas tirgus internetā. Miljoniem preču, tūkstošiem pārdevēju, viena uzticama adrese.', et:'Leedu moodsaim e-turg. Miljoneid tooteid, tuhandeid müüjaid, üks usaldusväärne aadress.', fi:'Liettuan moderneim verkkokauppapaikka. Miljoonia tuotteita, tuhansia myyjiä, yksi luotettava osoite.' },
    foot_shop:{ lt:'Pirkti', en:'Shop', ru:'Покупки', lv:'Iepirkties', et:'Pood', fi:'Osta' },
    foot_all_cats:{ lt:'Visos kategorijos', en:'All categories', ru:'Все категории', lv:'Visas kategorijas', et:'Kõik kategooriad', fi:'Kaikki kategoriat' },
    foot_deals:{ lt:'Pasiūlymai', en:'Deals', ru:'Предложения', lv:'Piedāvājumi', et:'Pakkumised', fi:'Tarjoukset' },
    foot_bestsellers:{ lt:'Bestseleriai', en:'Bestsellers', ru:'Бестселлеры', lv:'Bestselleri', et:'Bestsellerid', fi:'Bestsellerit' },
    foot_new:{ lt:'Naujienos', en:'New arrivals', ru:'Новинки', lv:'Jaunumi', et:'Uudised', fi:'Uutuudet' },
    foot_help:{ lt:'Pagalba', en:'Help', ru:'Помощь', lv:'Palīdzība', et:'Abi', fi:'Apu' },
    foot_company:{ lt:'Įmonė', en:'Company', ru:'Компания', lv:'Uzņēmums', et:'Ettevõte', fi:'Yritys' },
    foot_about:{ lt:'Apie mus', en:'About us', ru:'О нас', lv:'Par mums', et:'Meist', fi:'Tietoa meistä' },
    foot_contacts:{ lt:'Kontaktai', en:'Contacts', ru:'Контакты', lv:'Kontakti', et:'Kontaktid', fi:'Yhteystiedot' },
    foot_terms:{ lt:'Taisyklės', en:'Terms', ru:'Правила', lv:'Noteikumi', et:'Tingimused', fi:'Ehdot' },
    foot_privacy:{ lt:'Privatumas', en:'Privacy', ru:'Конфиденциальность', lv:'Privātums', et:'Privaatsus', fi:'Tietosuoja' },
    foot_newsletter:{ lt:'Naujienlaiškis', en:'Newsletter', ru:'Рассылка', lv:'Jaunumi', et:'Uudiskiri', fi:'Uutiskirje' },
    foot_news_p:{ lt:'Gaukite išskirtinius pasiūlymus pirmieji.', en:'Get exclusive deals first.', ru:'Получайте эксклюзивные предложения первыми.', lv:'Saņemiet ekskluzīvus piedāvājumus pirmie.', et:'Saa eksklusiivsed pakkumised esimesena.', fi:'Saa eksklusiiviset tarjoukset ensimmäisenä.' },
    foot_subscribed:{ lt:'Ačiū! Užsiprenumeravote naujienlaiškį.', en:'Thanks! You\u2019re subscribed.', ru:'Спасибо! Вы подписаны.', lv:'Paldies! Jūs esat pierakstījies.', et:'Aitäh! Oled tellinud.', fi:'Kiitos! Tilaus vahvistettu.' },
    login_btn:        { lt:'Prisijungti', en:'Sign in', ru:'Войти', lv:'Pieteikties', et:'Logi sisse', fi:'Kirjaudu' },
    no_account_q:     { lt:'Neturite paskyros?', en:"Don't have an account?", ru:'Нет аккаунта?', lv:'Nav konta?', et:'Pole kontot?', fi:'Eikö tiliä?' },
    register_link:    { lt:'Registruotis', en:'Register', ru:'Зарегистрироваться', lv:'Reģistrēties', et:'Registreeru', fi:'Rekisteröidy' },
    register_title:   { lt:'Registracija', en:'Registration', ru:'Регистрация', lv:'Reģistrācija', et:'Registreerimine', fi:'Rekisteröityminen' },
    name_label:       { lt:'Vardas', en:'First name', ru:'Имя', lv:'Vārds', et:'Eesnimi', fi:'Etunimi' },
    surname_label:    { lt:'Pavardė', en:'Last name', ru:'Фамилия', lv:'Uzvārds', et:'Perekonnanimi', fi:'Sukunimi' },
    password_min:     { lt:'Slaptažodis (min. 6)', en:'Password (min. 6)', ru:'Пароль (мин. 6)', lv:'Parole (min. 6)', et:'Parool (min. 6)', fi:'Salasana (väh. 6)' },
    repeat_password:  { lt:'Pakartoti slaptažodį', en:'Repeat password', ru:'Повторите пароль', lv:'Atkārtot paroli', et:'Korda parooli', fi:'Toista salasana' },
    passwords_mismatch:{ lt:'Slaptažodžiai nesutampa!', en:'Passwords do not match!', ru:'Пароли не совпадают!', lv:'Paroles nesakrīt!', et:'Paroolid ei kattu!', fi:'Salasanat eivät täsmää!' },
    password_too_short:{ lt:'Slaptažodis per trumpas!', en:'Password is too short!', ru:'Пароль слишком короткий!', lv:'Parole ir pārāk īsa!', et:'Parool on liiga lühike!', fi:'Salasana on liian lyhyt!' },
    have_account_q:   { lt:'Jau turite paskyrą?', en:'Already have an account?', ru:'Уже есть аккаунт?', lv:'Jau ir konts?', et:'Juba on konto?', fi:'Onko jo tili?' },
    delivery_method:  { lt:'Pristatymo būdas', en:'Delivery method', ru:'Способ доставки', lv:'Piegādes veids', et:'Tarneviis', fi:'Toimitustapa' },
    select_delivery:  { lt:'Pasirinkite pristatymą...', en:'Select delivery...', ru:'Выберите доставку...', lv:'Izvēlieties piegādi...', et:'Valige tarne...', fi:'Valitse toimitus...' },
    courier_opt:      { lt:'Kurjeriu (+5.90 €)', en:'Courier (+5.90 €)', ru:'Курьером (+5.90 €)', lv:'Ar kurjeru (+5.90 €)', et:'Kulleriga (+5.90 €)', fi:'Kuriirilla (+5.90 €)' },
    post_opt:         { lt:'Paštomatas (+4.90 €)', en:'Parcel locker (+4.90 €)', ru:'Постомат (+4.90 €)', lv:'Pakomāts (+4.90 €)', et:'Pakiautomaat (+4.90 €)', fi:'Pakettiautomaatti (+4.90 €)' },
    bus_opt:          { lt:'Autobusų siuntos (+7.00 €)', en:'Bus parcel (+7.00 €)', ru:'Автобусная доставка (+7.00 €)', lv:'Autobusu sūtījumi (+7.00 €)', et:'Bussisaadetised (+7.00 €)', fi:'Linja-autolähetykset (+7.00 €)' },
    terminal_search_placeholder:{ lt:'Įveskite miestą arba gatvę...', en:'Enter city or street...', ru:'Введите город или улицу...', lv:'Ievadiet pilsētu vai ielu...', et:'Sisestage linn või tänav...', fi:'Anna kaupunki tai katu...' },
    contact_details:  { lt:'Kontaktiniai duomenys', en:'Contact details', ru:'Контактные данные', lv:'Kontaktinformācija', et:'Kontaktandmed', fi:'Yhteystiedot' },
    order_summary:    { lt:'Užsakymo suvestinė', en:'Order summary', ru:'Сводка заказа', lv:'Pasūtījuma kopsavilkums', et:'Tellimuse kokkuvõte', fi:'Tilauksen yhteenveto' },
    delivery_address: { lt:'Pristatymo adresas', en:'Delivery address', ru:'Адрес доставки', lv:'Piegādes adrese', et:'Tarneaadress', fi:'Toimitusosoite' },
    want_invoice_label:{ lt:'Noriu gauti PVM sąskaitą faktūrą įmonei', en:'I want a VAT invoice for a company', ru:'Хочу получить счёт-фактуру для компании', lv:'Vēlos saņemt PVN rēķinu uzņēmumam', et:'Soovin ettevõttele käibemaksuarvet', fi:'Haluan ALV-laskun yritykselle' },
    invoice_company_name:{ lt:'Įmonės pavadinimas', en:'Company name', ru:'Название компании', lv:'Uzņēmuma nosaukums', et:'Ettevõtte nimi', fi:'Yrityksen nimi' },
    invoice_company_code:{ lt:'Įmonės kodas', en:'Company code', ru:'Код компании', lv:'Uzņēmuma kods', et:'Ettevõtte kood', fi:'Yrityksen koodi' },
    invoice_vat_code: { lt:'PVM kodas (jei yra)', en:'VAT code (if applicable)', ru:'Код НДС (если есть)', lv:'PVN kods (ja ir)', et:'KM-kohustuslase number (kui on)', fi:'ALV-tunniste (jos on)' },
    invoice_company_address:{ lt:'Įmonės adresas', en:'Company address', ru:'Адрес компании', lv:'Uzņēmuma adrese', et:'Ettevõtte aadress', fi:'Yrityksen osoite' },
    city_label:       { lt:'Miestas', en:'City', ru:'Город', lv:'Pilsēta', et:'Linn', fi:'Kaupunki' },
    zip_label:        { lt:'Pašto kodas', en:'ZIP code', ru:'Почтовый индекс', lv:'Pasta indekss', et:'Postiindeks', fi:'Postinumero' },
    street_label:     { lt:'Gatvė, namo nr.', en:'Street, house no.', ru:'Улица, дом №', lv:'Iela, mājas nr.', et:'Tänav, maja nr.', fi:'Katu, talon nro' },
    phone_label:      { lt:'Telefonas', en:'Phone', ru:'Телефон', lv:'Tālrunis', et:'Telefon', fi:'Puhelin' },
    items_label:      { lt:'Prekės:', en:'Items:', ru:'Товары:', lv:'Preces:', et:'Tooted:', fi:'Tuotteet:' },
    shipping_label:   { lt:'Pristatymas:', en:'Shipping:', ru:'Доставка:', lv:'Piegāde:', et:'Tarne:', fi:'Toimitus:' },
    confirm_order:    { lt:'Patvirtinti užsakymą', en:'Confirm order', ru:'Подтвердить заказ', lv:'Apstiprināt pasūtījumu', et:'Kinnita tellimus', fi:'Vahvista tilaus' },
    cart_is_empty_full:{ lt:'Krepšelis tuščias', en:'Your cart is empty', ru:'Ваша корзина пуста', lv:'Jūsu grozs ir tukšs', et:'Teie ostukorv on tühi', fi:'Ostoskorisi on tyhjä' },
    return_to_shop:   { lt:'Grįžti į parduotuvę', en:'Return to shop', ru:'Вернуться в магазин', lv:'Atgriezties veikalā', et:'Tagasi poodi', fi:'Palaa kauppaan' },
    select_delivery_alert:{ lt:'Pasirinkite pristatymo būdą!', en:'Please select a delivery method!', ru:'Выберите способ доставки!', lv:'Lūdzu, izvēlieties piegādes veidu!', et:'Palun valige tarneviis!', fi:'Valitse toimitustapa!' },
    fill_field_alert: { lt:'Užpildykite lauką:', en:'Please fill in field:', ru:'Заполните поле:', lv:'Lūdzu, aizpildiet lauku:', et:'Palun täida väli:', fi:'Täytä kenttä:' },
    select_terminal_alert:{ lt:'Pasirinkite paštomatą iš sąrašo!', en:'Please select a parcel locker from the list!', ru:'Выберите постомат из списка!', lv:'Lūdzu, izvēlieties pakomātu no saraksta!', et:'Palun valige pakiautomaat loetelust!', fi:'Valitse pakettiautomaatti listalta!' },
    order_save_error: { lt:'Nepavyko išsaugoti užsakymo. Bandykite dar kartą.', en:'Failed to save the order. Please try again.', ru:'Не удалось сохранить заказ. Попробуйте снова.', lv:'Neizdevās saglabāt pasūtījumu. Lūdzu, mēģiniet vēlreiz.', et:'Tellimuse salvestamine ebaõnnestus. Palun proovige uuesti.', fi:'Tilauksen tallennus epäonnistui. Yritä uudelleen.' },
    searching:        { lt:'Ieškoma...', en:'Searching...', ru:'Поиск...', lv:'Meklē...', et:'Otsimine...', fi:'Etsitään...' },
    start_typing_city:{ lt:'Pradėkite rašyti miestą arba gatvę', en:'Start typing a city or street', ru:'Начните вводить город или улицу', lv:'Sāciet rakstīt pilsētu vai ielu', et:'Alustage linna või tänava sisestamist', fi:'Aloita kaupungin tai kadun kirjoittaminen' },
    no_terminals_found:{ lt:'Nerasta paštomatų pagal', en:'No parcel lockers found for', ru:'Постоматы не найдены по запросу', lv:'Nav atrasti pakomāti pēc', et:'Pakiautomaate ei leitud otsingule', fi:'Pakettiautomaatteja ei löytynyt hakusanalle' },

    // ── Completed ─────────────────────────────────────────────────
    order_received:   { lt:'Užsakymas gautas!', en:'Order received!', ru:'Заказ получен!', lv:'Pasūtījums saņemts!', et:'Tellimus on saadud!', fi:'Tilaus vastaanotettu!' },
    thank_you_order:  { lt:'Ačiū! Jūsų užsakymas sėkmingai priimtas.', en:'Thank you! Your order has been successfully placed.', ru:'Спасибо! Ваш заказ успешно принят.', lv:'Paldies! Jūsu pasūtījums ir veiksmīgi pieņemts.', et:'Aitäh! Teie tellimus on edukalt vastu võetud.', fi:'Kiitos! Tilauksesi on vastaanotettu onnistuneesti.' },
    order_number:     { lt:'Užsakymo nr.', en:'Order no.', ru:'№ заказа', lv:'Pasūtījuma nr.', et:'Tellimuse nr.', fi:'Tilausnumero' },
    email_sent_to:    { lt:'El. laiškas su patvirtinimu išsiųstas į', en:'A confirmation email has been sent to', ru:'Письмо с подтверждением отправлено на', lv:'E-pasts ar apstiprinājumu nosūtīts uz', et:'Kinnituskiri on saadetud aadressile', fi:'Vahvistussähköposti on lähetetty osoitteeseen' },
    parcel_locker_label:{ lt:'Paštomatas:', en:'Parcel locker:', ru:'Постомат:', lv:'Pakomāts:', et:'Pakiautomaat:', fi:'Pakettiautomaatti:' },
    order_not_found:  { lt:'Užsakymo informacija nerasta.', en:'Order information not found.', ru:'Информация о заказе не найдена.', lv:'Pasūtījuma informācija netika atrasta.', et:'Tellimuse teave ei leitud.', fi:'Tilaustietoja ei löytynyt.' },

    // ── Track ─────────────────────────────────────────────────────
    track_title:      { lt:'Užsakymo sekimas', en:'Order tracking', ru:'Отслеживание заказа', lv:'Pasūtījuma sekošana', et:'Tellimuse jälgimine', fi:'Tilauksen seuranta' },
    track_sub:        { lt:'Įveskite užsakymo numerį, kurį gavote patvirtinus užsakymą', en:'Enter the order number you received when confirming your order', ru:'Введите номер заказа, который вы получили при подтверждении заказа', lv:'Ievadiet pasūtījuma numuru, ko saņēmāt, apstiprinot pasūtījumu', et:'Sisestage tellimuse number, mille saite tellimuse kinnitamisel', fi:'Anna tilausnumero, jonka sait tilauksen vahvistuksen yhteydessä' },
    search_btn:       { lt:'Ieškoti', en:'Search', ru:'Искать', lv:'Meklēt', et:'Otsi', fi:'Hae' },
    order_not_found_track:{ lt:'Užsakymas nerastas', en:'Order not found', ru:'Заказ не найден', lv:'Pasūtījums netika atrasts', et:'Tellimust ei leitud', fi:'Tilausta ei löytynyt' },
    tracking_number:  { lt:'Sekimo numeris', en:'Tracking number', ru:'Номер для отслеживания', lv:'Sekošanas numurs', et:'Jälgimisnumber', fi:'Seurantanumero' },
    download_invoice: { lt:'Atsisiųsti PVM sąskaitą', en:'Download VAT invoice', ru:'Скачать счёт-фактуру', lv:'Lejupielādēt PVN rēķinu', et:'Laadi alla käibemaksuarve', fi:'Lataa ALV-lasku' },
    download_credit_invoice:{ lt:'Atsisiųsti kreditinę sąskaitą', en:'Download credit invoice', ru:'Скачать кредитный счёт', lv:'Lejupielādēt kredītrēķinu', et:'Laadi alla kreeditarve', fi:'Lataa hyvityslasku' },
    reset_password_title:{ lt:'Naujas slaptažodis', en:'New password', ru:'Новый пароль', lv:'Jauna parole', et:'Uus parool', fi:'Uusi salasana' },
    reset_password_sub:{ lt:'Įveskite naują slaptažodį savo paskyrai.', en:'Enter a new password for your account.', ru:'Введите новый пароль для вашей учётной записи.', lv:'Ievadiet jaunu paroli savam kontam.', et:'Sisestage konto uus parool.', fi:'Anna uusi salasana tilillesi.' },
    reset_success_sub:{ lt:'Galite prisijungti su nauju slaptažodžiu.', en:'You can now sign in with your new password.', ru:'Теперь вы можете войти с новым паролем.', lv:'Tagad varat pieteikties ar jauno paroli.', et:'Nüüd saate sisse logida uue parooliga.', fi:'Voit kirjautua sisään uudella salasanalla.' },
    invalid_reset_link:{ lt:'Nuoroda negaliojanti arba pasenusi', en:'Link is invalid or expired', ru:'Ссылка недействительна или просрочена', lv:'Saite nav derīga vai ir beigusies', et:'Link on kehtetu või aegunud', fi:'Linkki on virheellinen tai vanhentunut' },
    my_addresses_tab:{ lt:'Mano adresai', en:'My addresses', ru:'Мои адреса', lv:'Mani adresi', et:'Minu aadressid', fi:'Omat osoitteet' },
    saved_addresses_title:{ lt:'Išsaugoti adresai', en:'Saved addresses', ru:'Сохранённые адреса', lv:'Saglabātās adreses', et:'Salvestatud aadressid', fi:'Tallennetut osoitteet' },
    add_new_address_btn:{ lt:'Pridėti naują adresą', en:'Add new address', ru:'Добавить новый адрес', lv:'Pievienot jaunu adresi', et:'Lisa uus aadress', fi:'Lisää uusi osoite' },
    address_label_field:{ lt:'Pavadinimas (pvz. Namai)', en:'Label (e.g. Home)', ru:'Название (напр. Дом)', lv:'Nosaukums (piem. Mājas)', et:'Nimetus (nt Kodu)', fi:'Nimi (esim. Koti)' },
    cancel_btn:{ lt:'Atšaukti', en:'Cancel', ru:'Отмена', lv:'Atcelt', et:'Loobu', fi:'Peruuta' },
    no_saved_addresses:{ lt:'Adresų dar nėra.', en:'No addresses yet.', ru:'Адресов пока нет.', lv:'Adresu vēl nav.', et:'Aadresse veel ei ole.', fi:'Osoitteita ei vielä ole.' },
    confirm_delete_address:{ lt:'Ar tikrai ištrinti šį adresą?', en:'Delete this address?', ru:'Удалить этот адрес?', lv:'Dzēst šo adresi?', et:'Kustuta see aadress?', fi:'Poista tämä osoite?' },
    use_saved_address:{ lt:'Naudoti išsaugotą adresą', en:'Use saved address', ru:'Использовать сохранённый адрес', lv:'Lietot saglabāto adresi', et:'Kasuta salvestatud aadressi', fi:'Käytä tallennettua osoitetta' },
    new_address_option:{ lt:'+ Naujas adresas', en:'+ New address', ru:'+ Новый адрес', lv:'+ Jauna adrese', et:'+ Uus aadress', fi:'+ Uusi osoite' },
    save_address_checkbox:{ lt:'Išsaugoti šį adresą ateičiai', en:'Save this address for later', ru:'Сохранить этот адрес на будущее', lv:'Saglabāt šo adresi turpmākai izmantošanai', et:'Salvesta see aadress hilisemaks', fi:'Tallenna tämä osoite tulevaa varten' },
    forgot_password_link:{ lt:'Pamiršote slaptažodį?', en:'Forgot password?', ru:'Забыли пароль?', lv:'Aizmirsāt paroli?', et:'Unustasid parooli?', fi:'Unohditko salasanan?' },
    forgot_password_title:{ lt:'Slaptažodžio atstatymas', en:'Password reset', ru:'Восстановление пароля', lv:'Paroles atjaunošana', et:'Parooli taastamine', fi:'Salasanan palautus' },
    forgot_password_sub:{ lt:'Įveskite savo el. paštą — atsiųsime nuorodą slaptažodžio atstatymui.', en:'Enter your email — we will send a password reset link.', ru:'Введите свою электронную почту — мы отправим ссылку для восстановления пароля.', lv:'Ievadiet savu e-pastu — nosūtīsim saiti paroles atjaunošanai.', et:'Sisestage oma e-post — saadame parooli lähtestamise lingi.', fi:'Anna sähköpostiosoitteesi — lähetämme salasanan palautuslinkin.' },
    send_reset_link_btn:{ lt:'Siųsti nuorodą', en:'Send link', ru:'Отправить ссылку', lv:'Sūtīt saiti', et:'Saada link', fi:'Lähetä linkki' },
    needs_verification_msg:{ lt:'Patvirtinkite el. paštą — patikrinkite savo pašto dėžutę.', en:'Please verify your email — check your inbox.', ru:'Подтвердите свою электронную почту — проверьте почтовый ящик.', lv:'Apstipriniet savu e-pastu — pārbaudiet savu pastkasti.', et:'Kinnitage oma e-post — kontrollige postkasti.', fi:'Vahvista sähköpostiosoitteesi — tarkista sähköpostisi.' },
    check_your_email:{ lt:'Patikrinkite savo el. paštą', en:'Check your email', ru:'Проверьте свою электронную почту', lv:'Pārbaudiet savu e-pastu', et:'Kontrollige oma e-posti', fi:'Tarkista sähköpostisi' },
    verification_email_sent_to:{ lt:'Patvirtinimo nuoroda išsiųsta į', en:'Verification link sent to', ru:'Ссылка для подтверждения отправлена на', lv:'Apstiprinājuma saite nosūtīta uz', et:'Kinnituslink saadetud aadressile', fi:'Vahvistuslinkki lähetetty osoitteeseen' },
    order_no_label:   { lt:'Užsakymo nr.:', en:'Order no.:', ru:'№ заказа:', lv:'Pasūtījuma nr.:', et:'Tellimuse nr.:', fi:'Tilausnumero:' },
    date_label:       { lt:'Data:', en:'Date:', ru:'Дата:', lv:'Datums:', et:'Kuupäev:', fi:'Päivämäärä:' },
    delivery_label:   { lt:'Pristatymas:', en:'Delivery:', ru:'Доставка:', lv:'Piegāde:', et:'Tarne:', fi:'Toimitus:' },
    sum_label:        { lt:'Suma:', en:'Amount:', ru:'Сумма:', lv:'Summa:', et:'Summa:', fi:'Summa:' },
    error_try_later:  { lt:'Klaida. Bandykite vėliau.', en:'Error. Please try again later.', ru:'Ошибка. Попробуйте позже.', lv:'Kļūda. Lūdzu, mēģiniet vēlāk.', et:'Viga. Palun proovige hiljem.', fi:'Virhe. Yritä myöhemmin.' },
    status_new:       { lt:'Naujas', en:'New', ru:'Новый', lv:'Jauns', et:'Uus', fi:'Uusi' },
    status_processing:{ lt:'Apdorojamas', en:'Processing', ru:'В обработке', lv:'Tiek apstrādāts', et:'Töötlemisel', fi:'Käsittelyssä' },
    status_shipped:   { lt:'Išsiųsta', en:'Shipped', ru:'Отправлен', lv:'Nosūtīts', et:'Saadetud', fi:'Lähetetty' },
    status_delivered: { lt:'Pristatyta', en:'Delivered', ru:'Доставлен', lv:'Piegādāts', et:'Kohale toimetatud', fi:'Toimitettu' },
    status_cancelled: { lt:'Atšaukta', en:'Cancelled', ru:'Отменён', lv:'Atcelts', et:'Tühistatud', fi:'Peruutettu' },

    // ── Account ───────────────────────────────────────────────────
    login_to_account_title:{ lt:'Prisijunkite prie paskyros', en:'Sign in to your account', ru:'Войдите в аккаунт', lv:'Pieteikties kontā', et:'Logi oma kontosse', fi:'Kirjaudu tilillesi' },
    login_to_account_sub:{ lt:'Prisijunkite, kad galėtumėte peržiūrėti savo užsakymus ir valdyti duomenis.', en:'Sign in to view your orders and manage your details.', ru:'Войдите, чтобы просматривать заказы и управлять данными.', lv:'Pieteikties, lai apskatītu pasūtījumus un pārvaldītu datus.', et:'Logi sisse, et vaadata tellimusi ja haldada andmeid.', fi:'Kirjaudu sisään nähdäksesi tilauksesi ja hallita tietojasi.' },
    welcome_back:     { lt:'Sveiki,', en:'Welcome,', ru:'Добро пожаловать,', lv:'Sveicināti,', et:'Tere,', fi:'Tervetuloa,' },
    account_sub:      { lt:'Čia galite peržiūrėti užsakymus ir keisti duomenis.', en:'Here you can view orders and update your details.', ru:'Здесь вы можете просматривать заказы и обновлять данные.', lv:'Šeit jūs varat apskatīt pasūtījumus un atjaunināt datus.', et:'Siin saate vaadata tellimusi ja muuta andmeid.', fi:'Tässä voit tarkastella tilauksiasi ja päivittää tietojasi.' },
    my_orders_tab:    { lt:'Mano užsakymai', en:'My orders', ru:'Мои заказы', lv:'Mani pasūtījumi', et:'Minu tellimused', fi:'Omat tilaukset' },
    profile_tab:      { lt:'Profilis', en:'Profile', ru:'Профиль', lv:'Profils', et:'Profiil', fi:'Profiili' },
    password_tab:     { lt:'Slaptažodis', en:'Password', ru:'Пароль', lv:'Parole', et:'Parool', fi:'Salasana' },
    contact_data:     { lt:'Kontaktiniai duomenys', en:'Contact details', ru:'Контактные данные', lv:'Kontaktinformācija', et:'Kontaktandmed', fi:'Yhteystiedot' },
    save_btn:         { lt:'Išsaugoti', en:'Save', ru:'Сохранить', lv:'Saglabāt', et:'Salvesta', fi:'Tallenna' },
    change_password_title:{ lt:'Keisti slaptažodį', en:'Change password', ru:'Изменить пароль', lv:'Mainīt paroli', et:'Muuda parooli', fi:'Vaihda salasana' },
    current_password: { lt:'Dabartinis slaptažodis', en:'Current password', ru:'Текущий пароль', lv:'Pašreizējā parole', et:'Praegune parool', fi:'Nykyinen salasana' },
    new_password:     { lt:'Naujas slaptažodis', en:'New password', ru:'Новый пароль', lv:'Jaunā parole', et:'Uus parool', fi:'Uusi salasana' },
    repeat_new_password:{ lt:'Pakartoti naują', en:'Repeat new', ru:'Повторите новый', lv:'Atkārtot jauno', et:'Korda uut', fi:'Toista uusi' },
    change_btn:       { lt:'Pakeisti', en:'Change', ru:'Изменить', lv:'Mainīt', et:'Muuda', fi:'Vaihda' },
    saved_msg:        { lt:'Išsaugota!', en:'Saved!', ru:'Сохранено!', lv:'Saglabāts!', et:'Salvestatud!', fi:'Tallennettu!' },
    password_changed: { lt:'Slaptažodis pakeistas!', en:'Password changed!', ru:'Пароль изменён!', lv:'Parole nomainīta!', et:'Parool muudetud!', fi:'Salasana vaihdettu!' },
    no_orders_yet:    { lt:'Užsakymų dar nėra.', en:'No orders yet.', ru:'Заказов пока нет.', lv:'Pasūtījumu vēl nav.', et:'Tellimusi veel ei ole.', fi:'Tilauksia ei vielä ole.' },
    account_unavailable:{ lt:'Paskyra nepasiekiama', en:'Account unavailable', ru:'Аккаунт недоступен', lv:'Konts nav pieejams', et:'Konto ei ole saadaval', fi:'Tili ei ole käytettävissä' },
    account_unavailable_sub:{ lt:'Šis puslapis veikia tik serveryje su PHP palaikymu.', en:'This page only works on a server with PHP support.', ru:'Эта страница работает только на сервере с поддержкой PHP.', lv:'Šī lapa darbojas tikai serverī ar PHP atbalstu.', et:'See leht töötab ainult PHP-toega serveris.', fi:'Tämä sivu toimii vain PHP-tuella palvelimella.' },

    // ── Info puslapiai ────────────────────────────────────────────
    delivery_page_title:{ lt:'Pristatymas', en:'Delivery', ru:'Доставка', lv:'Piegāde', et:'Kohaletoimetamine', fi:'Toimitus' },
    payments_page_title:{ lt:'Saugūs mokėjimai', en:'Secure payments', ru:'Безопасные платежи', lv:'Droši maksājumi', et:'Turvalised maksed', fi:'Turvalliset maksut' },
    returns_page_title:{ lt:'Grąžinimai', en:'Returns', ru:'Возвраты', lv:'Atgriešana', et:'Tagastamine', fi:'Palautukset' },
    support_page_title:{ lt:'Klientų aptarnavimas', en:'Customer support', ru:'Поддержка клиентов', lv:'Klientu atbalsts', et:'Klienditeenindus', fi:'Asiakaspalvelu' },

    // ── Footer ────────────────────────────────────────────────────
    all_rights:       { lt:'Visos teisės saugomos.', en:'All rights reserved.', ru:'Все права защищены.', lv:'Visas tiesības aizsargātas.', et:'Kõik õigused kaitstud.', fi:'Kaikki oikeudet pidätetään.' },
};

/**
 * Grąžina vertimą pagal raktą ir esamą kalbą.
 * Jei raktas nerastas — grąžina raktą pačiu (lengviau pastebėti trūkstamus vertimus).
 */
function t(key) {
    const lang = currentLangCode();
    const entry = I18N_DICT[key];
    if (!entry) return key;
    return entry[lang] || entry.lt || key;
}

function currentLangCode() {
    return localStorage.getItem('shop_lang') || 'lt';
}

/**
 * Pritaiko vertimus visiems elementams su data-i18n="raktas" atributu.
 * Taip pat tvarko data-i18n-placeholder (input placeholder vertimui).
 */
function applyI18n() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        el.textContent = t(key);
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        el.setAttribute('placeholder', t(key));
    });
    document.querySelectorAll('[data-i18n-html]').forEach(el => {
        const key = el.getAttribute('data-i18n-html');
        el.innerHTML = t(key);
    });
}

/**
 * Sukuria kalbos jungiklio HTML (su vėliavomis) į nurodytą konteinerį.
 * onChangeCallback kviečiamas po kalbos pakeitimo (pvz. produktų perkrovimui).
 */
function renderLangSwitcher(containerId, onChangeCallback) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const current = currentLangCode();
    container.innerHTML = I18N_LANGS.map(code => `
        <button class="lang-pill${code===current?' active':''}" data-lang="${code}" onclick="setLang('${code}')" title="${I18N_NAMES[code]}">
            <span class="fi fi-${I18N_FLAGS[code]} lang-flag"></span><span class="lang-code">${code.toUpperCase()}</span>
        </button>
    `).join('');
    if (onChangeCallback) container.dataset.callback = 'registered';
    window.__i18nChangeCallback = onChangeCallback || window.__i18nChangeCallback;
}

function setLang(lang) {
    if (!I18N_LANGS.includes(lang)) lang = 'lt';
    localStorage.setItem('shop_lang', lang);
    document.querySelectorAll('.lang-pill').forEach(b => b.classList.toggle('active', b.dataset.lang === lang));
    applyI18n();
    if (typeof window.__i18nChangeCallback === 'function') {
        window.__i18nChangeCallback(lang);
    }
}
