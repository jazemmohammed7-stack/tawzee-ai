# Tawzee AI — تشغيل بيئة التطوير

مشروع Laravel القائم؛ لا تستخدم create-project أو composer setup لإعادة تهيئته. حالة التنفيذ وأدلة Phase 0 في [TASKS.md](TASKS.md)، وتعليمات الوكيل في [AGENTS.md](AGENTS.md).

## التشغيل المحلي

من جذر المشروع، بعد ضبط `.env` محليًا دون مشاركته:

```powershell
composer install
npm.cmd ci
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
```

لا تشغّل نسخة ثانية إذا كان المنفذ مشغولًا. الصفحة تعرض بداية عربية عامة، ويمكن تجربة زر التفاعل دون تسجيل دخول. `npm.cmd` يتجنب حظر npm.ps1 في PowerShell دون تغيير Execution Policy.

## MySQL المحلي المؤقت

الخادم الفعلي XAMPP MariaDB 10.4.32 عبر pdo_mysql وInnoDB. القاعدتان `tawzee_dev` و`tawzee_test` أُنشئتا بعد فحص التعارضات، بحسابين مستقلين `tawzee_dev_app` و`tawzee_test_app` مقيدين بـ127.0.0.1 وبقاعدتهما فقط. التطبيق لا يستخدم root. لا تستبدل `.env` أو `.env.testing` القائمين؛ كلمات المرور محلية ومستثناة من Git. ملف SQLite السابق محفوظ ولم تُنقل بياناته أو تُحذف.

على جهاز جديد: جهز القاعدتين والحسابين بأداة إدارة موثوقة، واضبط `.env` وفق `.env.example`، و`.env.testing` وفق `.env.testing.example`. اضبط هوية التطوير DB_DATABASE/DB_USERNAME في ملف الاختبار دون نسخ كلمة مرور التطوير، ليقارن الحارس الاتصالين. لا تضع كلمات المرور في الأوامر أو Git. حساب الاختبار يحتاج صلاحيات الجداول على قاعدته فقط دون صلاحيات global أو GRANT OPTION. escape للشرطة السفلية في GRANT (`tawzee\_test`) يمنع مطابقة أسماء قواعد أخرى.

```powershell
php artisan config:clear
php scripts/inspect-database.php
php scripts/inspect-database.php --testing
php artisan migrate:status
php artisan migrate:status --env=testing --database=mysql_testing
```

بعد نجاح الاتصال وهوية القاعدة ومراجعة الجداول الموجودة فقط:

```powershell
php artisan migrate --no-interaction
php artisan migrate --env=testing --database=mysql_testing --no-interaction
composer test:mysql
```

لا fresh/wipe/reset. اختبارات MySQL تستخدم معاملات تعاد بعد كل اختبار ولا تعيد إنشاء القاعدة. `php scripts/check-test-migrations.php` فحص خاص بترحيلات P1-T01 الثلاثة: يتحقق من هوية وصلاحيات قاعدة الاختبار وخلو جداول المستأجر وسجل الترحيلات، ثم يتراجع عن الثلاثة ويعيدها دون مساس بقاعدة التطوير. يتوقف إذا تغيرت الشروط؛ لا تستخدمه على بيانات تريد الاحتفاظ بها.

## PostgreSQL — مؤجل إلى P8-T08

PostgreSQL ما زال الهدف قبل الإطلاق. اتصال `pgsql_testing` و`phpunit.postgres.xml` واختبارات PostgreSQL محفوظة، وأضيفت إليها اختبارات مخطط الشركات المشتركة. فحوص MySQL لا تثبت نجاح PostgreSQL.

عند تنفيذ P8-T08: جهز خادمًا وقاعدتين ودورين مستقلين، وفعل pdo_pgsql. مرجع إعداد الاختبار `.env.postgres.testing.example`: أضف PG_TEST_DB_* إلى `.env.testing` المحلي دون استبدال مفاتيح TEST_MYSQL_*. افحص اتصال PostgreSQL وهويته وترحيلاته قبل تشغيل الاختبارات أو النقل. شغّل `composer test:postgres` بعد الترحيلات على قاعدة PostgreSQL الاختبارية المعزولة؛ الامتداد يمكن تحميله مؤقتًا بـ `php -d extension=pdo_pgsql vendor/bin/phpunit -c phpunit.postgres.xml`. لا تُعلّم P8-T08 مكتملة قبل اختبارات المحرك ونقل تجريبي وخطة رجوع وفق TASKS.md.

## فحوص الجودة

```powershell
composer validate --strict
composer check-platform-reqs
composer test
composer lint
composer test:mysql
npm.cmd run build
```

`composer test` يشغّل اختبارات الواجهة والسلامة باستخدام SQLite في الذاكرة، ولا يثبت سلوك MySQL أو PostgreSQL. حارس Tests/DatabaseSafety يرفض اتصالات غير اختبارية قبل تجهيز الاختبار. `composer lint` يشغّل Pint وفحص PHP syntax؛ ليس تحليلًا دلاليًا متقدمًا.

خط Noto Sans Arabic من حزمة @fontsource، برخصة SIL OFL المرفقة في الحزمة؛ يُبنى ويُقدّم محليًا دون CDN. سجلات التوقيت UTC، والعرض الافتراضي Asia/Aden.

---

## مرجع Laravel الأصلي

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


## تشغيل المصادقة واستعادة كلمة المرور — P1-T04

- `/login` للدخول، `/forgot-password` لطلب الاستعادة، `/reset-password/{token}` للرابط، و`/pending-setup` وجهة مؤقتة محمية. الخروج POST فقط عبر النموذج مع CSRF. صفحة التسجيل بقيت على `/register` وتعرض رابط الدخول بعد النجاح.
- يستخدم الدخول Laravel SessionGuard، والاستعادة Password Broker وجدول password_reset_tokens الموجود. لا ترحيلات أو حزم إضافية.
- **الإرسال الحقيقي غير مفعّل افتراضيًا ولم يُختبر.** الطلب يعرض رسالة عامة سواء كان البريد موجودًا أم غير موجود. لا يصدر رمزًا ولا يرسل بريدًا إذا كان `PASSWORD_RESET_MAIL_ENABLED=false` أو لم يكن mailer هو `smtp`؛ يمنع ذلك تسجيل الرموز عبر mailer `log` المحلي.
- عند تجهيز بريد التشغيل وبالتفويض المناسب: اضبط محليًا `MAIL_MAILER=smtp` و`MAIL_HOST` و`MAIL_PORT` و`MAIL_SCHEME` حسب مزود SMTP، ثم `MAIL_USERNAME` و`MAIL_PASSWORD` و`MAIL_FROM_ADDRESS` و`MAIL_FROM_NAME`. راجع `MAIL_URL` إن كان مضبوطًا لأنه قد يغيّر إعداد الاتصال. لا تحفظ بيانات الدخول في Git.
- اضبط `APP_URL` إلى الأصل الموثوق عبر HTTPS، ثم فعّل `PASSWORD_RESET_MAIL_ENABLED=true` وامسح/أعد بناء config cache في بيئة التشغيل المعتادة. الروابط تستخدم APP_URL ولا تثق بـHost الطلب. الاختبارات تستخدم Mail::fake ولا تثبت وصول SMTP.
- قبل النشر: `APP_DEBUG=false` و`SESSION_SECURE_COOKIE=true` مع HTTPS، مع إبقاء HttpOnly وSameSite مفعّلين. لا تسجل محتوى طلبات Livewire أو كلمات المرور؛ احجب مسار الرمز `/reset-password/*` واستعلام البريد من سجلات الوصول في الخادم/الوكيل وأي أدوات مراقبة. القالب يستخدم no-referrer وصفحات المصادقة no-store.
- صلاحية الرمز 60 دقيقة وإعادة إصداره بعد 60 ثانية وفق config/auth.php. كل مسار من الدخول/طلب الرابط/تعيين كلمة المرور له حد 5 محاولات لكل بريد مطبّع وIP، و20 محاولة لكل IP، خلال دقيقة؛ الحدود لا تعتمد على وجود الحساب.
- تغيير كلمة المرور لا يسجل الدخول، ولا يغير الشركة أو is_active أو الصلاحيات؛ يعيد المستخدم إلى الدخول. المستخدم المعطل لا تصدر له رسالة ولا يُقبل رمزه حتى لو أُصدر قبل التعطيل.
- صفحة الانتظار تفحص نشاط المستخدم مجددًا وتستخدم auth.session لإبطال الجلسات ذات كلمة المرور القديمة. **P1-T05 اكتملت:** الحماية معممّة على طلبات web وLivewire، وسياق الشركة محكوم بدورة الطلب؛ تفاصيل الترتيب والحدود في ARCHITECTURE §17. هذه الصفحة ليست Dashboard ولا تفتح ميزات تجارية لشركة pending_setup.


### حماية المسارات بعد P1-T05

كل مسار يتعامل مع بيانات الشركة يستخدم `auth` و`tenant`. المسارات التجارية عند إنشائها تحتاج أيضًا `company.ready` وPolicy المناسبة بعد P2؛ الجاهزية وحدها لا تمنح صلاحيات. صفحة `/pending-setup` مستثناة من الجاهزية حتى تبقى متاحة للشركة الجديدة. طبّق Middleware على المسار الأصلي لمكونات Livewire؛ persistent middleware يعيد التحقق عند updates، ومجموعة web تغلف التنفيذ بسياق الشركة وتنظفه. لا تسجل SetCurrentCompany كـpersistent middleware، ولا تمرر company_id في query/body/header/cookie/public property. لا توجد مسارات أعمال جديدة ضمن P1-T05.


### مساعد اختبارات عزل الشركات — P1-T06

استخدم `Tests\Concerns\InteractsWithTenantIsolation` داخل اختبار يرث `Tests\TestCase` ويستخدم `DatabaseTransactions`. حارس DatabaseSafety يبقى في TestCase؛ المساعد لا يغيّر الاتصال ولا يعطّل Scope. مثال على المورد المنفذ حاليًا:

```php
use InteractsWithTenantIsolation;

$pair = $this->tenantPair(fn () => DocumentSequence::create(['type' => 'invoice']));
$this->assertTenantReadIsolation($pair, fn ($row) => DocumentSequence::find($row->id));
$this->assertTenantUpdateIsolation(
    $pair,
    fn ($row) => DocumentSequence::whereKey($row->id)->update(['next_number' => 4]),
    ['next_number' => 4],
);
$this->assertTenantDeleteIsolation($pair, fn ($row) => DocumentSequence::whereKey($row->id)->delete());
```

- `tenantPair($create)` يعيد userA/userB/resourceA/resourceB، بشركتين ومستخدمين مستقلين؛ يستدعي factory المورد داخل السياق الموثوق لكل مستخدم ويتحقق أن الموردين محفوظان. الدالة تتلقى User إن احتجت إنشاء علاقات تابعة له. اجعل الموردين في حالة أعمال متكافئة تسمح بالعملية، وأضف أدوار الاختبار الملائمة عند تنفيذ P2.
- `withinTenant($user, $callback)` يبدل هوية الاختبار ويفتح CurrentCompany::run مؤقتًا، ويعيد الهوية عند النجاح أو الاستثناء. `storedTenantResource($user, $model)` يعيد قراءة المورد من DB بسياق صاحبه وشرط company_id صريح. لا `withoutGlobalScopes` ولا قراءة عامة لكل الشركات.
- `assertTenantReadIsolation` يحتاج callback يُرجع Model أو null؛ يثبت قراءة A أولًا ثم غياب B. لا تمرر findOrFail إلى هذا المساعد؛ استخدم مساعد HTTP/Binding لمسار يعيد 404.
- `assertTenantUpdateIsolation` يثبت تغير حقول A المحددة فعلًا ثم يحاول تحديث B ويتحقق من ثبات **كامل صف B**. `assertTenantDeleteIsolation` يثبت حذف A ثم بقاء صف B بكل قيمه. استخدم pair جديدًا عند احتياج المورد A بعد اختبار الحذف.
- `assertTenantHttpIsolation($pair, $request, $identity)` يستقبل دالة طلب حسب المورد ودالة تستخرج معرف المورد من الاستجابة؛ يتطلب نجاح A ومعرفه الصحيح، ثم 404 بلا معرف مورد لـB، مع بقاء B دون تغيير. مثال: `$request = fn ($row) => $this->getJson('/_helper/'.$row->id)` و`$identity = fn ($response) => $response->json('id')`، على مسار اختباري محمي مسجل داخل الاختبار فقط. الصفحات ذات تنسيق مختلف تحتاج مستخرج هوية مناسبًا؛ ليست أي استجابة 200 دليل وصول.
- لـLivewire، مرّر إلى `assertTenantUpdateIsolation` دالة ترسل POST HTTP الحقيقي إلى update endpoint بـsnapshot صادر من الصفحة المحمية؛ المثال التنفيذي في `TenantIsolationHelperTest::test_helper_exercises_real_livewire_update_and_foreign_rejection`. يستخدم TenantProbe القائم، ويثبت تغير A وثبات B. لا يعتمد إثبات Middleware على Livewire::test الذي يعطّل Middleware أثناء المحاكاة.
- `assertTenantCreationRequiresContext($pair, $create)` يُستدعى كضيف، بنفس factory صالح في الحالتين: يثبت إنشاءً صحيحًا في A ثم رفض الإنشاء دون سياق وعدم ظهور كتابة في A أو B.
- `assertTenantInjectionRejected($pair, $validCreate, $injectedCreate)` يثبت إنشاءً طبيعيًا، ثم يمرر company_id الخاص بـB إلى callback غير الصالح ويتحقق من الرفض ومن عدم تغير صفوف المورد في الشركتين. على مستوى Eloquent استخدم forceFill لمحاكاة إسناد ملكية ممنوع؛ create العادي يتجاهل الحقل غير fillable وفق سياسة التطبيق القائمة، فلا تخلط تجاهل الحقل برفض الطلب الصريح.
- `assertTenantContextClosesOnException($user)` يُستدعى كضيف ويثبت السياق الصحيح داخل callback، انتشار الاستثناء نفسه، ثم غياب الهوية والسياق بعده.

نتائج المنع المقبولة في اختبارات الكتابة: AuthorizationException/ModelNotFoundException/ValidationException أو HTTP 403/404/422 أو صفر صفوف/false؛ **لا تكفي وحدها** دون العملية الصحيحة على A وفحص DB النهائي لـB. الاستثناءات الأخرى تنتشر ولا تتحول إلى نجاح. عمليات النموذج ذات soft-delete أو قواعد الحذف المختلفة تحتاج assertions مناسبة لحالتها عند تنفيذها؛ لم تُختبر موارد مستقبلية هنا.

`TenantIsolationHelperTest` يثبت قدرة المساعد على اكتشاف فقدان العزل: Fixture غير محمي على جدول الاختبار نفسه، تحديث/حذف فعلي يعيد صفرًا كاذبًا، كتابة ثم استثناء منع، قراءة فارغة للجميع، 404 للجميع، هوية استجابة خاطئة، و404 يحتوي هوية المورد. يتوقع صنف AssertionFailedError ورسالة موضع الفشل في الحالات العدائية، لا مجرد أي استثناء. كل التغييرات العدائية على سجلات fixtures فقط داخل transaction تتراجع؛ لا تعطيل عام لحماية التطبيق ولا واجهات إنتاجية جديدة.
