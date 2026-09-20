---
name: laravel-development
description: Implement a Tawzee AI feature end-to-end in Laravel following the project's modular architecture. Use when creating or changing models, migrations, actions, Livewire components, validation, policies, Arabic RTL views or feature tests for any task in TASKS.md.
---

# مهارة تنفيذ ميزات Laravel — Tawzee AI

تُستخدم مع [security-quality](../security-quality/SKILL.md) (للتحقق قبل الإنهاء). القواعد الحاكمة في [AGENTS.md](../../../AGENTS.md)، والتصميم في [ARCHITECTURE.md](../../../ARCHITECTURE.md). **هذه المهارة تصف خطوات التنفيذ فقط ولا تكرر القواعد.**

## المدخلات
معرّف مهمة من [TASKS.md](../../../TASKS.md) تبعياتها مكتملة.

## الخطوات

### 1. تحليل المتطلبات
1. اقرأ المهمة ومعايير قبولها واختباراتها في TASKS.md.
2. اقرأ من [PROJECT.md](../../../PROJECT.md) قواعد الأعمال `BR-*` ومعايير `AC-*` ذات الصلة والأدوار/الصلاحيات.
3. اكتب خطة قصيرة: الوحدة المالكة، الجداول، الـ Actions، الشاشات، الصلاحيات، الاختبارات.
4. **تحقق:** كل معيار قبول له اختبار مخطط، ولا شيء في الخطة خارج MVP.

### 2. تصميم قاعدة البيانات
1. ابدأ من جدول الكيانات في [ARCHITECTURE.md §3](../../../ARCHITECTURE.md#3-الكيانات-والعلاقات-الأساسية)؛ أي انحراف يُوثَّق هناك أولًا.
2. أعمدة `company_id` (مفهرس، مفتاح أجنبي)، والمبالغ `bigint` بأسماء `_minor`، والكميات `numeric(15,3)`.
3. كل `UNIQUE` يبدأ بـ `company_id`. أضف `CHECK` وفهارس تخدم الاستعلامات الفعلية (`company_id` أول عمود).
4. لا حذف مادي للسجلات المالية/المخزونية.
5. **تحقق:** الترحيل يعمل صعودًا ونزولًا على قاعدة الاختبار، وأسماء الجداول والأعمدة تطابق الوثيقة.

### 3. تنفيذ Models وMigrations
1. الترحيل أولًا، ثم النموذج داخل مجلد الوحدة.
2. النموذج: `BelongsToCompany`، `$fillable` صريح (لا `$guarded = []`)، `casts` مناسبة، Enums للحالات، علاقات مسماة بوضوح.
3. حقول المال عبر `Money`، وليست float أبدًا.
4. لا منطق أعمال معقد في النموذج؛ فقط علاقات ونطاقات (scopes) وحراس حالة بسيطة.
5. أضف Factory بحالات مفيدة (شركة معلومة، حالات مؤكدة/ملغاة).
6. **تحقق:** `company_id` لا يظهر في `$fillable`، والـ Factory يعمل مع الشركة الحالية.

### 4. Actions ثم Controllers/Livewire
1. **Action** لكل عملية ذات أثر: كلاس بدالة `handle()` بمدخلات مكتوبة الأنواع، تتحقق من الحالة (State Guards)، وتنفذ داخل `DB::transaction()` عند تغيير أكثر من جدول ([§8](../../../ARCHITECTURE.md#8-database-transactions))، وتعتمد Idempotency للعمليات المالية ([§9](../../../ARCHITECTURE.md#9-منع-العمليات-المالية-المكررة-idempotency)).
2. **Livewire:** يجمع المدخلات ويستدعي Action، ولا يحتوي منطق أعمال. الخصائص العامة (`public`) قابلة للتلاعب من العميل: لا تضع فيها `company_id` أو مبالغ محسوبة أو حالة، وأعد الحساب في الخادم.
3. استخدم `#[Locked]` للمعرّفات الحساسة، و`wire:key` للقوائم، و`wire:loading.attr="disabled"` على أزرار الإرسال.
4. **Controller** فقط لما لا يناسب Livewire (تنزيل ملف، طباعة، تصدير).
5. Pagination في القوائم، والـ Eager Loading للعلاقات المعروضة.
6. **تحقق:** لا استعلام داخل حلقة عرض، ولا منطق أعمال في Blade.

### 5. Validation وAuthorization
1. **Validation** في Form Request (Controllers) أو `#[Validate]`/Form Object (Livewire). القواعد المقيدة بالشركة: `Rule::exists('products','id')->where('company_id', $companyId)` و`Rule::unique(...)->where('company_id', ...)`.
2. الرسائل بالعربية من `lang/ar`، وأسماء الحقول (`attributes`) معرّبة.
3. **Authorization:** Policy لكل مورد، وأسطر `$this->authorize()` صريحة في **كل** Livewire action وكل Controller، وليس في `mount` فقط.
4. أضف الصلاحية الجديدة (إن وُجدت) إلى Enum `Permission` وSeeder والمصفوفة في [PROJECT.md](../../../PROJECT.md#مصفوفة-الصلاحيات-mvp) معًا.
5. **تحقق:** كل نقطة دخول جديدة تفوّض، وكل قاعدة `exists/unique` مقيدة بالشركة.

### 6. الواجهات العربية وRTL
1. Blade يمتد من Layout الرئيسي `dir="rtl"`. استخدم خصائص Tailwind المنطقية (`ms-`, `me-`, `ps-`, `pe-`, `text-start`, `text-end`) بدل `ml/mr/left/right`.
2. كل نص من `__('...')` في `lang/ar`، ولا نصوص مضمّنة.
3. الأرقام لاتينية؛ المبالغ عبر `Money::format()` ومحاذاتها لا تنكسر في RTL (استخدم `dir="ltr"` للأرقام/SKU/الهاتف عند الحاجة).
4. الجداول الواسعة داخل حاوية `overflow-x-auto`، والنماذج قابلة للاستخدام على الجوال.
5. حالات: تحميل، فراغ، خطأ، نجاح؛ ورسائل خطأ مفهومة لغير التقنيين.
6. **تحقق:** الصفحة سليمة على عرض 375px وعلى سطح المكتب، ولا نص إنجليزي ظاهر.

### 7. الاختبارات
1. **Feature (PHPUnit أو Pest المعتمد):** المسار السعيد، والرفض بالتحقق (422)، والتفويض (403 لكل دور غير مخوّل)، والعزل بين الشركات (404).
2. **Unit:** للحسابات (Money، التقريب، الإجماليات) والحراس.
3. للعمليات المالية/المخزونية: الحالات الحدية، والتكرار (Idempotency)، والتزامن حيث ينطبق، وفشل جزئي يلغي الكل.
4. اختبارات Livewire بـ `Livewire::test()` تتضمن استدعاء الأفعال بمستخدم غير مخوّل.
5. اسم الاختبار يصف السلوك ويربط `AC-*` حيث ينطبق.
6. **تحقق:** كل معايير القبول في TASKS.md لها اختبار يمر، والمجموعة الكاملة تمر.

### 8. مراجعة الأداء
1. لا N+1 (`Model::preventLazyLoading()` في بيئة التطوير/الاختبار).
2. فهارس تغطي الفلاتر والفرز، و`EXPLAIN` لأي استعلام تقرير جديد.
3. لا تحميل مجموعات كاملة؛ `chunk`/`cursor`/Pagination.
4. **تحقق:** عدد الاستعلامات في الصفحة ثابت لا يتناسب مع عدد الصفوف.

## معايير الإنجاز
المهمة تنتهي وفق **Definition of Done** في [AGENTS.md §6](../../../AGENTS.md#6-definition-of-done)، ثم مرّ بقائمة [security-quality](../security-quality/SKILL.md) قبل تعليمها `Done` في TASKS.md.

## أخطاء شائعة تُتجنب
- ثقة بقيم الإدخال للمبالغ أو `company_id` أو الحالة.
- تفويض في `mount` دون الأفعال.
- إنشاء Repository/Service/Interface دون حاجة.
- استخدام `float` أو `round()` في المال.
- تعديل ترحيل قديم بدل إضافة ترحيل جديد بعد أي دمج.
