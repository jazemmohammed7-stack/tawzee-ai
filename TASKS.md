# TASKS.md — خطة تنفيذ Tawzee AI

> **مصدر الحالة الوحيد.** قواعد التحديث في [AGENTS.md §7](AGENTS.md#7-تحديث-تقدم-المشروع). المتطلبات في [PROJECT.md](PROJECT.md) والتصميم في [ARCHITECTURE.md](ARCHITECTURE.md).

## الحالة الحالية

| البند | القيمة |
|---|---|
| **المرحلة الحالية** | المرحلة 2 — P2-T01 إلى P2-T04 مكتملة على MariaDB؛ المرحلتان 0 و1 مكتملتان |
| **المهمة التالية** | `P2-T05` — إعدادات الشركة؛ لم يبدأ |
| **آخر تحديث** | 2026-09-26 — P2-T04: Done على MariaDB |

**الحالات:** `Not Started` · `In Progress` · `Blocked` · `Done`. حالة كل مهمة موضحة في جدولها؛ لم يبدأ تنفيذ الوحدات التجارية.
**الدليل:** عند `Done` تُضاف سطر «دليل» أسفل جدول المرحلة: الأمر الفعلي ونتيجته.

## التبعيات بين المراحل

```
P0 ──► P1 ──► P2 ──► P3 ──► P4 ──► P5 ──► P6 ──► P7 ──► P8
                      │      ▲      ▲
                      └──────┴──────┘   (P4 تحتاج Catalog، P5 تحتاج Customers + Catalog + Inventory)
```

| المرحلة | تعتمد على | سبب |
|---|---|---|
| P0 | — | أساس المشروع |
| P1 | P0 | المصادقة والـ Tenancy تحتاجان الهيكل والاختبار |
| P2 | P1 | الصلاحيات مقيدة بالشركة |
| P3 | P1, P2 | كيانات المستأجر بعزل وصلاحيات |
| P4 | P3 | الحركات تحتاج المنتجات |
| P5 | P3, P4 | الفاتورة تحتاج العملاء والمنتجات وخصم المخزون |
| P6 | P5 | التحصيل يوزع على الفواتير |
| P7 | P4, P5, P6 | التقارير على بيانات مكتملة |
| P8 | الكل | تثبيت MVP |

**اختبارات لكل المراحل:** كل مهمة تنفذ الاختبارات في عمودها، وتُضاف إليها اختبارات عزل الشركات وصلاحيات الأدوار لكل مورد جديد وفق [security-quality](.agents/skills/security-quality/SKILL.md). التنفيذ بمهارة [laravel-development](.agents/skills/laravel-development/SKILL.md).

---

## المرحلة 0 — تهيئة Laravel والبيئة

**الهدف:** مشروع يعمل ببنية وحدات وأدوات جودة وواجهة RTL أساسية.

| المعرف | المهمة | معيار القبول | الاختبارات | الحالة |
|---|---|---|---|---|
| P0-T01 | فحص البيئة ومراجعة Laravel الموجود دون إعادة إنشائه | `php artisan --version` يعمل؛ الإصدارات الفعلية موثقة في ARCHITECTURE.md؛ تشغيل الخادم المحلي ناجح | اختبار Feature افتراضي يمر | Done |
| P0-T02 | ضبط MySQL المؤقت و`.env.example` وقاعدة اختبار منفصلة؛ PostgreSQL مؤجل إلى P8-T08 | `migrate` ينجح محليًا؛ `.env` غير متتبَّع في Git؛ الاختبارات تعمل على قاعدة الاختبار | اختبار اتصال/ترحيل وصلاحيات منفصلة | Done |
| P0-T03 | تجهيز Livewire وTailwind والتحقق من PHPUnit الموجود | صفحة Livewire تجريبية تُعرض؛ `npm run build` ينجح؛ PHPUnit يعمل | اختبار Livewire بسيط | Done |
| P0-T04 | Layout عربي RTL: `lang="ar" dir="rtl"`، خط عربي محلي، ملف `lang/ar`، منطقة `Asia/Aden` للعرض | الصفحة الأساسية RTL ومتجاوبة؛ لا موارد خارجية إلزامية | اختبار عرض للتحقق من `dir="rtl"` | Done |
| P0-T05 | Pint وفحص PHP syntax وسكربتات Composer (`test`, `lint`)؛ Providers للوحدات عند تنفيذها فقط | أوامر الجودة تعمل وProvider التطبيق وLivewire يُحمّلان؛ لا هياكل تجارية فارغة | اختبار تحميل الـ Providers | Done |
| P0-T06 | دمج ملفات التأسيس ومراجعة العقود والروابط وسياسة الأسرار والتبعيات | الملفات الثمانية في الجذر ومسارات المهارات صحيحة؛ JSON صالح؛ الأصل محفوظ | فحص JSON والروابط وبنية SKILL.md | Done |

## المرحلة 1 — Authentication + Tenancy

**يعتمد على:** P0. **الهدف:** شركات ومستخدمون بعزل مضمون.

| المعرف | المهمة | معيار القبول | الاختبارات | الحالة |
|---|---|---|---|---|
| P1-T01 | Migrations/Models: `companies`, `users` (+`company_id`, `is_active`) و`document_sequences` | القيود والفهارس كما في ARCHITECTURE §3 | Unit للنماذج والعلاقات | Done |
| P1-T02 | `BelongsToCompany` + `CompanyScope` + `CurrentCompany` | ملء `company_id` تلقائيًا؛ استعلامات مقيدة؛ 404 عبر Route Binding؛ رفض الإنشاء بلا سياق شركة | Feature: عزل بين شركتين؛ محاولة تجاوز `company_id` من الإدخال | Done |
| P1-T03 | التسجيل الأساسي في Transaction: الشركة والمستخدم والتسلسلات فقط؛ تعيين owner بعد P2-T02، والتهيئة التابعة في P4-T08 | فشل أي جزء يلغي الكل؛ لا مستخدم بلا شركة | Feature: نجاح/فشل جزئي/بريد مكرر | Done |
| P1-T04 | تسجيل الدخول والخروج واستعادة كلمة المرور مع Rate Limiting؛ منع المعطّلين | AC: مستخدم معطّل لا يدخل؛ محاولات كثيرة تُحجب | Feature: دخول/رفض/تحديد المعدل/تعطيل | Done |
| P1-T05 | Middleware يضبط الشركة الحالية من المستخدم فقط، وشاشة رئيسية بعد الدخول | لا يمكن ضبط الشركة من URL/Header | Feature: محاولة انتحال شركة | Done |
| P1-T06 | أدوات اختبار عزل الشركات (Helpers/Traits) قابلة لإعادة الاستخدام | مساعد واحد يثبت أن مورد B غير مرئي لمستخدم A | Unit/Feature للمساعد نفسه | Done |
| P1-T07 | سجل النشاط `activity_logs` وخدمة تسجيل بسيطة | تسجيل الفاعل والإجراء والموضوع بلا بيانات حساسة | Feature: يُسجَّل ويُعزل بين الشركات | Done |

## المرحلة 2 — Roles & Permissions

**يعتمد على:** P1.

| المعرف | المهمة | معيار القبول | الاختبارات | الحالة |
|---|---|---|---|---|
| P2-T01 | تقييم/تثبيت حزمة الصلاحيات مع Teams بحسب التوافق (أو بديل بسيط)، وتوثيق القرار | الحزمة تعمل بـ `company_id` كـ Team؛ القرار مسجل في ARCHITECTURE §10 | Feature: دور في شركة لا يؤثر في أخرى | Done |
| P2-T02 | Enum `Permission` وSeeder الأدوار الافتراضية وفق [المصفوفة](PROJECT.md#مصفوفة-الصلاحيات-mvp)، ويُستدعى لتهيئة الأدوار بعد التسجيل الأساسي | كل شركة جديدة لها الأدوار الستة | Feature: مطابقة المصفوفة حرفيًا (Data-driven) | Done |
| P2-T03 | قاعدة Policies وأنماط `authorize()` في Livewire/Controllers | نمط موثق ومثال عامل | Feature: 403 على كل دور غير مخوّل | Done |
| P2-T04 | إدارة المستخدمين (FR-03): إنشاء/تعديل/تعطيل/إسناد دور داخل الشركة | مالك الشركة لا يُعطَّل ولا يُنزَّل دوره بواسطة غيره؛ لا يرى مستخدمي شركات أخرى | Feature: صلاحيات + عزل + حماية المالك | Done |
| P2-T05 | صفحة إعدادات الشركة (`allow_negative_stock`, الاسم) | تتطلب `company.settings`؛ تُسجَّل في سجل النشاط | Feature: تفويض + تسجيل نشاط | Not Started |
| P2-T06 | اختبار مصفوفة الصلاحيات الشامل على المسارات القائمة | لا مسار محمي بلا اختبار دور | Feature: مصفوفة دور × مسار | Not Started |

## المرحلة 3 — Customers & Products

**يعتمد على:** P1, P2.

| المعرف | المهمة | معيار القبول | الاختبارات | الحالة |
|---|---|---|---|---|
| P3-T00 | Money وHalf-Up ودوال الكميات (BCMath)، قبل P3-T01/P3-T02 وقبل P4/P5 | أعداد صحيحة دون float | Unit: جمع/طرح/ضرب/تقريب/حدود/رفض عملات مختلفة | Not Started |
| P3-T01 | العملاء: Migration/Model/Actions/Livewire (قائمة، بحث، إنشاء، تعديل، تعطيل) مع حد ائتماني | كود فريد ضمن الشركة؛ البحث بالاسم/الهاتف/الكود؛ العميل النقدي محمي من الحذف والتعطيل | Feature: CRUD + عزل + صلاحيات + تحقق | Not Started |
| P3-T02 | تصنيفات المنتجات والمنتجات: CRUD، SKU فريد ضمن الشركة، أسعار بالوحدة الصغرى | إدخال السعر يُحوَّل عبر `Money`؛ لا float؛ التصنيف من الشركة نفسها فقط | Feature/Unit: تحقق SKU، تصنيف شركة أخرى مرفوض | Not Started |
| P3-T03 | قواعد التعطيل: لا حذف منتج له حركات، ولا حذف عميل له فواتير (BR-I4) | التعطيل بدل الحذف مع رسالة عربية | Feature: محاولة حذف مرفوضة | Not Started |
| P3-T04 | قوائم بـ Pagination وفهارس `(company_id, ...)` | لا N+1؛ فهارس مبررة | Feature: استعلامات معدودة | Not Started |

## المرحلة 4 — Inventory

**يعتمد على:** P3.

| المعرف | المهمة | معيار القبول | الاختبارات | الحالة |
|---|---|---|---|---|
| P4-T01 | المستودعات: CRUD ومستودع افتراضي واحد | مستودع افتراضي واحد فقط؛ عزل بالشركة | Feature: CRUD + عزل + صلاحيات | Not Started |
| P4-T02 | جداول `stock_balances`, `stock_operations`, `stock_movements` وAction `RecordStockMovement` بأقفال وترتيب ثابت | حركات Append-only؛ رصيد يتحدث في المعاملة؛ رفض السالب حسب الإعداد | Unit/Feature: حركات، سالب، إعادة المحاولة عند deadlock | Not Started |
| P4-T03 | استلام مخزون/رصيد افتتاحي (Action + Livewire) | كل بند يعطي حركة؛ الأرقام تنسيقية بلا float | Feature: استلام صحيح، كمية ≤ 0 مرفوضة، عزل | Not Started |
| P4-T04 | تسوية زيادة/نقص بسبب إلزامي | لا نقص يتجاوز الرصيد؛ سجل نشاط | Feature: تسوية + سبب + نشاط | Not Started |
| P4-T05 | تحويل بين مستودعين في عملية واحدة (AC-12) | مجموع الصنف ثابت؛ فشل جزء يلغي الكل؛ لا مستودع من شركة أخرى | Feature: نجاح/فشل/عزل | Not Started |
| P4-T06 | صفحات أرصدة المخزون وسجل حركة الصنف بتصفية | مطابقة الأرصدة لمجموع الحركات | Feature: عرض + تصفية + عزل | Not Started |
| P4-T07 | أمر/اختبار المطابقة `Reconcile` بين الحركات والأرصدة + اختبار تزامن | لا عدم تطابق بعد عمليات متزامنة (AC-05 جزئيًا) | Feature: تزامن باستخدام عمليات متوازية | Not Started |
| P4-T08 | تهيئة الشركة بعد P1-T03 وP2-T02 وP3-T01 وP4-T01: ربط الأدوار والعميل النقدي والمستودع الافتراضي بخدمات وحداتها | تهيئة قابلة لإعادة المحاولة بلا تكرار؛ لا تفعيل تجاري قبل اكتمالها؛ فشل التهيئة يبقي الحالة pending_setup | Feature: فشل جزئي/إعادة محاولة/شركة قديمة وجديدة/لا تكرار | Not Started |

## المرحلة 5 — Sales & Invoices

**يعتمد على:** P3, P4.

| المعرف | المهمة | معيار القبول | الاختبارات | الحالة |
|---|---|---|---|---|
| P5-T01 | ترقيم تسلسلي `NextDocumentNumber` بقفل الصف | بلا تكرار ولا فجوات؛ لكل شركة/نوع | Feature: تزامن + عزل بين الشركات | Not Started |
| P5-T02 | جداول `invoices`, `invoice_items` ونماذجها وحراس الحالة | قيود ومفاتيح حسب ARCHITECTURE §3؛ منع التعديل بعد التأكيد | Unit: انتقالات الحالة | Not Started |
| P5-T03 | مسودة الفاتورة: إنشاء/تعديل/حذف بمعرفة بنود بواجهة Livewire عربية | مسودة لا تؤثر على المخزون؛ اختيار صنف من الشركة نفسها فقط | Feature: CRUD + عزل + صلاحيات | Not Started |
| P5-T04 | Action حساب الإجماليات (خصم بند/فاتورة، تقريب) | BR-S2 و AC-13؛ الإجماليات لا تأتي من العميل | Unit: حالات حدية وتقريب | Not Started |
| P5-T05 | `ConfirmInvoice`: قفل، فحص كفاية، حركات `sale_out`، ترقيم، Snapshot، Idempotency، الحد الائتماني | AC-03/04/05/06 و BR-S7 | Feature: نجاح/مخزون غير كافٍ/تزامن/تكرار/حد ائتماني/غير مخوّل | Not Started |
| P5-T06 | فصل التأكيد عن التحصيل؛ تأجيل paid_now إلى P6-T08 | تأكيد مستقل دون جداول التحصيل؛ رفض paid_now غير الصفري حتى P6-T08 | Feature: تأكيد دون تحصيل ورفض الدفع المبكر | Not Started |
| P5-T07 | `CancelInvoice` بسبب إلزامي | AC-10؛ حركات `sale_cancel_in`؛ رفض paid_minor غير الصفري؛ AC-09 بالتوزيعات الفعلية في P6-T08 | Feature: إلغاء/رفض رصيد مدفوع/مؤكدة فقط دون جدول collections | Not Started |
| P5-T08 | فاتورة الرصيد الافتتاحي `opening_balance` | بلا مخزون؛ ترقيم منفصل؛ تظهر في كشف الحساب | Feature: إنشاء + عزل + صلاحية | Not Started |
| P5-T09 | قائمة الفواتير مع تصفية وعرض وطباعة A4 عربية | الطباعة تعرض Snapshot لا بيانات حالية | Feature: تصفية + عزل + عرض الطباعة | Not Started |

## المرحلة 6 — Receivables & Collections

**يعتمد على:** P5.

| المعرف | المهمة | معيار القبول | الاختبارات | الحالة |
|---|---|---|---|---|
| P6-T01 | جداول `collections`, `collection_allocations` ونماذجها | قيود وIdempotency فريدة | Unit للنماذج | Not Started |
| P6-T02 | `RecordCollection`: توزيع يدوي/تلقائي (الأقدم أولًا) وتحديث `paid/balance/payment_status` | AC-07/08؛ لا يتجاوز الدين؛ Idempotency | Feature: جزئي/كلي/زائد/تكرار/قفل | Not Started |
| P6-T03 | واجهة تسجيل التحصيل وإيصال قابل للطباعة | ترقيم تسلسلي للإيصال؛ اختيار فواتير العميل نفسه فقط | Feature: واجهة + عزل + صلاحيات | Not Started |
| P6-T04 | `VoidCollection` بسبب إلزامي وعكس التوزيع | يعيد الدين ولا يحذف؛ سجل نشاط | Feature: إلغاء + إعادة حساب | Not Started |
| P6-T05 | كشف حساب العميل وأرصدة العملاء | AC-11: تطابق الأرصدة | Feature: مطابقة أرصدة بسيناريو متعدد | Not Started |
| P6-T06 | قائمة الديون وأعمار الديون (0–30/31–60/61–90/90+) | التصنيف حسب الاستحقاق (أو الإصدار إن غاب) | Unit/Feature: حدود الفئات | Not Started |
| P6-T07 | اختبار اتساق شامل: `paid_minor` = Σ التوزيعات الفعّالة | لا اختلاف بعد سلسلة عمليات عشوائية | Feature/Property-style | Not Started |
| P6-T08 | الدفع الفوري بعد P6-T01/P6-T02/P6-T04: منسّق Receivables يستدعي ConfirmInvoice ثم RecordCollection بمعاملة واحدة | BR-R2؛ لا اعتماد Sales على Receivables؛ AC-09 مع تحصيل فعلي | Feature: كامل/جزئي/زائد/rollback/تكرار/منع إلغاء فاتورة محصلة | Not Started |

## المرحلة 7 — Dashboard & Reports

**يعتمد على:** P4, P5, P6. **الوحدة للقراءة فقط.**

| المعرف | المهمة | معيار القبول | الاختبارات | الحالة |
|---|---|---|---|---|
| P7-T01 | لوحة التحكم (مبيعات اليوم/الشهر، تحصيل اليوم، إجمالي الديون، منخفض المخزون، أعلى العملاء والمنتجات) | الأرقام تطابق البيانات؛ تستثني الملغاة والمسودات | Feature: أرقام بسيناريو معلوم + عزل | Not Started |
| P7-T02 | تقرير المبيعات (فترة/عميل/منتج) | AC-14 | Feature: مطابقة المجاميع | Not Started |
| P7-T03 | تقرير الديون وأعمارها | يطابق P6-T06 | Feature | Not Started |
| P7-T04 | تقارير المخزون (الأرصدة، القيمة بالتكلفة، حركة صنف، منخفض) | تقييم بـ `cost_price` مع تنبيه أنه تقديري (A-07) | Feature: مطابقة الأرصدة | Not Started |
| P7-T05 | تصدير CSV متدفق بـ `reports.export` | ترميز UTF-8 مع BOM لتوافق Excel العربي؛ ضمن نطاق الشركة | Feature: محتوى + صلاحية + عزل | Not Started |
| P7-T06 | فهارس وتحسين استعلامات التقارير | لا N+1؛ استعلامات مقاسة على بيانات كبيرة مولدة | Feature/أداء: حدود زمنية معقولة | Not Started |

## المرحلة 8 — Testing & MVP Stabilization

**يعتمد على:** الكل.

| المعرف | المهمة | معيار القبول | الاختبارات | الحالة |
|---|---|---|---|---|
| P8-T01 | سيناريو شامل من البداية للنهاية: منتج ← استلام ← فاتورة ← تحصيل جزئي ثم كلي ← تقرير | الأرقام متسقة عبر كل الشاشات | Feature E2E | Not Started |
| P8-T02 | مراجعة أمنية كاملة بقائمة security-quality (عزل كل مورد، صلاحيات كل مسار، Mass-Assignment، الأسرار) | لا ثغرة مفتوحة أو تُوثَّق بمبرر | Feature: مصفوفة العزل والصلاحيات | Not Started |
| P8-T03 | مراجعة الأداء: N+1، الفهارس، Pagination | أهداف NFR-03 مقاسة | اختبارات أداء على بيانات مولدة | Not Started |
| P8-T04 | مراجعة تجربة عربية RTL والجوال والرسائل | لا نص إنجليزي ظاهر؛ لا كسر في RTL | مراجعة يدوية موثقة + اختبارات عرض | Not Started |
| P8-T05 | بيانات تجريبية (Seeder Demo) بشركتين للعرض والاختبار | بيانات قابلة للحذف؛ لا تعمل في الإنتاج | Feature: Seeder يعمل | Not Started |
| P8-T06 | README تشغيل ونشر ونسخ احتياطي واستعادة (NFR-08) | خطوات قابلة للتنفيذ ومجربة | مراجعة يدوية + تجربة استعادة | Not Started |
| P8-T07 | مراجعة الافتراضات في PROJECT §9 وتحديث الوثائق | كل افتراض مؤكد أو مُحدَّث | — | Not Started |
| P8-T08 | الانتقال من MySQL المؤقت إلى PostgreSQL قبل الإطلاق النهائي | تشغيل كل الترحيلات واختبارات العزل والقيود والتزامن على PostgreSQL؛ نقل البيانات ومطابقة الأرصدة وخطة رجوع مجربة | PostgreSQL integration + migration rehearsal؛ MySQL ليس دليلًا بديلًا | Not Started |

---



## سجل الأدلة

> يُملأ عند إنجاز المهام فقط: `المعرف — التاريخ — الأمر — النتيجة`.

### Phase 0 — 2026-09-20

**الحالة: Partial.** P0-T01/P0-T03/P0-T04/P0-T05/P0-T06: Done. P0-T02: Blocked. لم تبدأ Phase 1.

- P0-T01: `php artisan --version` → Laravel 12.69.2؛ PHP 8.2.12، Composer 2.10.3، Node 22.23.2، npm 10.9.8. الاختباران الأصليان نجحا قبل التعديل. `composer validate --strict` و`composer check-platform-reqs` نجحا. `php artisan serve --host=127.0.0.1 --port=8000 --no-reload` بدأ بعد التأكد من عدم وجود مستمع؛ `Invoke-WebRequest http://127.0.0.1:8000/` → HTTP 200.
- P0-T02: `php scripts/inspect-database.php` → الاتصال الحالي SQLite ناجح؛ `php artisan migrate:status` → الترحيلات الأساسية الثلاثة Ran. لم تُعد الترحيلات ولم تُمس بيانات SQLite. لا خدمة PostgreSQL محلية/مستمع 5432 أو حاوية Docker قائمة؛ بيانات خادم مستقل لم تُقدّم. `php -m` لا يتضمن pdo_pgsql؛ `php -d extension=pdo_pgsql -m` أثبت إمكانية تحميله مؤقتًا. `composer test:postgres` → خطآن قبل الاتصال: حارس الأمان يرفض غياب TEST_DB_DATABASE/TEST_DB_USERNAME. لم يُختبر اتصال أو ترحيل PostgreSQL فعليًا. المطلوب: خادم وقاعدتا تطوير/اختبار ودوران منفصلان، ثم تنفيذ خطوات README وإعادة الاختبارات.
- P0-T03: تثبيت Livewire 4.4.5 وحده دون ترقية حزم Composer القائمة؛ تثبيت حزم npm المعلنة وخط محلي. `npm.cmd run build` → نجاح، 58 modules، ملفات CSS/JS وخطوط محلية. `composer test` → 8 passed، 20 assertions، تتضمن تفاعل Livewire.
- P0-T04: `node storage/app/private/phase0-browser.mjs` عبر Chrome headless → نجاح بعرض 375 و1440: lang=ar، RTL، لا overflow، الخط المحلي محمّل، زر Livewire يعمل، لا موارد خارجية ولا أخطاء JavaScript. تمت مراجعة الصورتين بصريًا: `storage/app/private/phase0-375.png` و`phase0-1440.png` (آثار تحقق محلية مستثناة من Git). تعثرت أداة المتصفح أولًا بسبب تبويب بدء التشغيل وترميز مقارنة النص، ثم أُصلحت وأعيد التحقق بنجاح.
- P0-T05: `composer lint` → Pint passed وPHP syntax: 34 files passed. اختبارات Foundation تتحقق من تحميل AppServiceProvider وLivewireServiceProvider. تم توثيق تأجيل Providers التجارية والتحليل الدلالي المتقدم؛ لا مجلدات وحدات فارغة.
- P0-T06: فحص Python لمسارات Markdown → 0 روابط ملفات مكسورة؛ JSON parse للأربعة tools/composer/package/pint → نجاح؛ frontmatter للمهارتين → نجاح. ملفات الحزمة الثمانية موجودة في الجذر والحزمة الأصلية محفوظة. AGENTS.md في الجذر هو نقطة الدخول؛ نسخة TASKS في الحزمة مرجعية فقط.

### مراجعة وأسباب التعديلات

- تغيّر P0-T01 من إنشاء Laravel إلى مراجعة الموجود بناءً على طلب المستخدم. احتُفظ بـPHPUnit بدل تثبيت Pest مكرر.
- تغيّر P0-T05 لتجنب إنشاء Providers ووحدات فارغة؛ فحص syntax مع Pint دون الادعاء بتحليل دلالي.
- انتقل منطق Money والكميات من P0-T06 إلى P3-T00 قبل العملاء والمنتجات. أصبح P0-T06 دمج الحزمة ومراجعتها وفق نطاق الطلب.
- P5-T06 صار فصل التأكيد عن التحصيل؛ الدفع الفوري في P6-T08 بعد جداول التحصيل وActions. اختبارات منع الإلغاء مع توزيعات فعلية في P6-T08، دون اعتماد دائري في P5.
- توحيد سياسة .env: إعداد محلي ضمن التفويض، لا عرض أسرار، ولا تجاوز لصلاحيات البيئة. .env.example و.env.testing.example بلا بيانات دخول. إعدادات اللغة والجلسات والكاش المحلية عُدلت دون تغيير APP_KEY أو بيانات الاتصال.
- تم إنشاء Git محلي لأن الفحص أثبت عدم وجود مستودع. لا commit ولا remote ولا push. `git -c safe.directory=D:/projects/laravel_projects/TAWZEE check-ignore ...` أكد تجاهل .env و.env.testing والنسخ الاحتياطية وauth.json وملفات البناء. جميع الملفات الجديدة غير متتبعة حتى يراجعها المستخدم؛ لم تُضف أسرار إلى index.
- عزل الشركات والتفويض والمال والمخزون: لم تُنفذ وحداتها، فلا ادعاء باختبارها. صفحة البداية عامة وزرها عديم الأثر على البيانات.

### الملفات الجديدة والمعدلة

- دمج: AGENTS.md، PROJECT.md، ARCHITECTURE.md، SYSTEM_PROMPT.md، TASKS.md، tools.schema.json، ومهارتا .agents/skills؛ تصحيحات متناسقة في الحزمة المحفوظة.
- واجهة: app/Livewire/Foundation.php، resources/views/foundation.blade.php، layouts/app.blade.php، livewire/foundation.blade.php، lang/ar/foundation.php، config/tawzee.php، routes/web.php، resources/css/app.css.
- إعداد وجودة: composer.json/lock، package.json/lock، config/app.php، config/database.php، .env.example، .env.testing.example، .gitignore، phpunit.xml، phpunit.postgres.xml، pint.json، scripts/lint.php، scripts/inspect-database.php، README.md. ملف .env المحلي المعدل مستثنى من Git.
- اختبارات: tests/TestCase.php، tests/DatabaseSafety.php، tests/Unit/DatabaseSafetyTest.php، tests/Feature/FoundationTest.php، tests/Postgres/ConnectionTest.php.

**ملاحظة تاريخية قبل اعتماد MySQL:** كانت P1-T01 تنتظر إغلاق P0-T02؛ الحالة الحالية في أعلى الملف والأدلة اللاحقة أدناه.


### دليل الانتقال إلى MySQL — 2026-09-20

P0-T02 Done على MariaDB 10.4.32 فقط: القاعدتان tawzee_dev/tawzee_test بحسابين مستقلين، الاتصال الفعلي والهوية وفشل الوصول المتبادل مثبتة قبل migrations؛ ثلاث migrations أساسية ناجحة لكل قاعدة. composer test:mysql: 21 tests / 39 assertions؛ composer test: 18 passed / 30 assertions؛ composer lint: Pint passed / 35 PHP files؛ npm.cmd run build: success. لا نجاح PostgreSQL مُدّعى؛ P8-T08 مؤجلة وشرط للإطلاق. الأدلة السابقة أعلاه محفوظة كتاريخ قبل القرار الجديد.

### P1-T01 — 2026-09-20 — Done

- بعد إغلاق P0-T02 على المحرك المؤقت فقط: أُضيفت ثلاث migrations متسلسلة لإنشاء companies، وإضافة users.company_id الإلزامي المفهرس وis_active، وإنشاء document_sequences بقيد فريد (company_id,type) وCHECK(next_number >= 1). مفاتيح أجنبية تمنع السجلات اليتيمة وحذف شركة مرتبطة. البريد يبقى فريدًا عالميًا.
- نماذج Company وDocumentSequence وعلاقاتها في وحدة Company، وتوسيع App/Models/User القائم مع Factory للشركة وربط UserFactory بها؛ لا شاشات تسجيل أو أدوار أو وحدات تجارية جديدة. company_id والحقول الحساسة خارج fillable.
- `php artisan migrate --no-interaction` ونسخته `--env=testing --database=mysql_testing` → الثلاثة DONE على التطوير والاختبار. `migrate:status` النهائي → ستة ترحيلات Ran لكل قاعدة.
- `php vendor/bin/phpunit -c phpunit.mysql.xml --testsuite=Database` → 13 tests / 32 assertions. يشمل شركتين بعلاقات منفصلة، المعرفات المزورة وmass assignment، FK وNOT NULL، تفرّد التسلسل داخل الشركة، البريد العالمي، رفض next_number=0، ومنع حذف الشركات المرتبطة.
- `php scripts/check-test-migrations.php` → rollback/reapply للترحيلات الثلاثة نجح على قاعدة الاختبار الفارغة والمؤكدة فقط؛ الجداول الأساسية محفوظة وقاعدة التطوير لم تتغير.
- `composer test:mysql` النهائي → 34 tests / 71 assertions؛ `composer test` → 18 passed / 30 assertions؛ `composer lint` → Pint passed، 43 PHP files passed. `composer validate --strict` ناجح. البناء نجح بعد إعداد MySQL؛ لم تتغير الواجهة بعده. HTTP المحلي أعاد 200.
- تحقق Git: .env و.env.testing وملف استرداد بيانات الإنشاء المؤقت مستثناة، ولا أسرار متتبعة. لم تُستخدم كلمات مرور مفترضة: الإدارة المحلية استُمدت من إعداد phpMyAdmin الصريح دون عرضه، والتطبيق والاختبار بحسابين مولدين غير root.
- PostgreSQL مؤجل: احتُفظ باختبارات المحرك وإعداداته مع فصل أسماء PG_TEST_DB_*؛ لم تُشغّل فحوصه في هذه الدورة، ولا يُعد نجاح MariaDB دليلًا على نجاحها. P8-T08 شرط للإطلاق النهائي.
- فصل P1-T03: التسجيل الأساسي لا يستدعي جداول مستقبلية؛ تهيئة الأدوار والعميل النقدي والمستودع في P4-T08 بعد تبعياتها. الشركة pending_setup حتى اكتمال التهيئة؛ اختبارات الإعادة والفشل الجزئي مطلوبة في تلك المهمة.
- نطاق العزل المثبت هنا هو العلاقات وقيود قاعدة البيانات. Global Scope وCurrentCompany وRoute Binding وتفويض المستخدمين لم تُنفذ، وهي P1-T02 وما بعدها. لا موارد مستأجر عامة أُضيفت.

**الملفات المعدلة/الجديدة في هذه الدورة:** config/database.php، .env.example، .env.testing.example، .env.postgres.testing.example، .gitignore، phpunit.mysql.xml، phpunit.postgres.xml، composer.json، tests/DatabaseSafety.php، tests/TestCase.php، tests/Unit/DatabaseSafetyTest.php، tests/MySql/ConnectionTest.php، tests/Database/CompanySchemaTest.php، scripts/inspect-database.php، scripts/check-test-migrations.php، migrations الثلاثة 2026_09_20، app/Modules/Company/Models/{Company,DocumentSequence}.php، app/Models/User.php، database/factories/{CompanyFactory,UserFactory}.php، AGENTS/PROJECT/ARCHITECTURE/TASKS/README. .env و.env.testing عُدّلا محليًا دون كشف أسرار. لا حزم جديدة أو ترقية تبعيات.

**العوائق الحالية:** لا عائق للمهمة التالية على MySQL؛ تجهيز واختبار PostgreSQL لا يزال مؤجلًا. **التالي P1-T02 فقط في دورة لاحقة**، ولم تُنفذ بقية Phase 1.

### المراجعة الأمنية النهائية — 2026-09-20

تعزيز الحارس للتحقق من هوية التطوير في .env الحقيقي (دون عرض قيم حساسة)، بما يشمل DB_URL، ورفض اختلاف حالة الأحرف الذي قد يشير إلى نفس القاعدة على Windows، ورفض انتحال اتصال SQLite بمحرك شبكي. بعد التعديل: composer test:mysql → **37 tests / 74 assertions**؛ composer test → **21 passed / 33 assertions**؛ composer lint → Pint passed / 43 PHP files passed. هذه هي النتائج النهائية، والأعداد السابقة محفوظة كأدلة مرحلية.

### P1-T02 — 2026-09-20 — Done

**الفحص قبل التعديل:** قراءة AGENTS/TASKS/ARCHITECTURE والمهارتين والكود الفعلي. Git ما زال دون commits والملفات غير متتبعة؛ لم تُحذف تغييرات أو تُعد تهيئة المستودع. `php scripts/inspect-database.php --testing` أكد MariaDB 10.4.32 وقاعدة tawzee_test بحساب tawzee_test_app و11 جدولًا، مع حواجز الاختبار القائمة. baseline `composer test:mysql` → 37 tests / 74 assertions، كلها ناجحة.

**التنفيذ:** CurrentCompany scoped بمصدر Auth أو run داخلي موثوق وfinally، وCompanyScope وBelongsToCompany على DocumentSequence. حراسة حفظ/حذف النماذج المحملة وتغيير الشركة، بما يشمل العمليات الصامتة؛ CompanyBuilder يحمي الكتابات الجماعية ويحظر إزالة نطاق الشركة والاختصارات غير الآمنة. User/Company استثناء داخلي محدود للمصادقة/التسجيل كما في ARCHITECTURE §14، دون واجهات مستأجر عامة. لا حزم أو migrations جديدة، ولا تغييرات على .env أو بيانات التطوير.

**التحقق النهائي:**
- `php vendor/bin/phpunit -c phpunit.mysql.xml --filter=TenancyTest` → **33 tests / 69 assertions، 0 failed**.
- `composer test:mysql` → **70 tests / 143 assertions، 0 failed** على قاعدة MySQL الاختبارية فقط.
- `composer test` → **21 passed / 33 assertions، 0 failed**؛ المجموعة العامة تستخدم SQLite في الذاكرة ولا تثبت عزل MariaDB وحدها.
- `composer lint` → Pint passed وPHP syntax: **48 files passed**.
- `php artisan route:list --except-vendor` → مسار الصفحة العامة فقط؛ لا مسارات مستأجر إنتاجية أُضيفت.

**التغطية:** مستخدم A لا يقرأ/يعدل/يحذف B؛ OR queries مقيدة؛ company_id المحقون لا يغيّر الشركة؛ غياب السياق يرفض القراءة والإنشاء؛ create/save/associate عبر شركة أخرى مرفوض؛ بيانات B تبقى سليمة بعد محاولات الحفظ الفاشلة؛ bulk owner changes/upsert وإزالة النطاق مرفوضة؛ own save/delete/insert ناجحة. fresh/refresh/restoration مقيدة. السياق يعاد بعد nested/escaping exceptions وعند بدء عملية داخلية تالية؛ scoped instance الجديدة لا ترث الحالة؛ تغير المستخدم المصادق عليه لا يترك شركة مخزنة؛ بيانات company_id غير المحفوظة على User لا تغيّر مصدر السياق. مزوّد المصادقة ما زال يسترجع المستخدمين ويتحقق من كلمة المرور دون Tenant Scope.

**Route Binding وحدود الإنجاز:** 404 لسجل B و200 لسجل A على مسار اختباري محلي مع Laravel web/auth وبمصدر Auth المباشر؛ محاولة company_id في query/header لا تتجاوز العزل. لا ادعاء باختبار Middleware P1-T05 أو تدفق دخول كامل أو Queue worker فعلي. P1-T05 ما زالت Not Started ويلزم فيها تكامل Middleware والمسارات المحمية وLivewire ودورة الطلب. P1-T04 للمصادقة المتكاملة وP2 لتفويض الأدوار.

**الملفات:** app/Support/Tenancy/{CurrentCompany,CompanyScope,BelongsToCompany,CompanyBuilder}.php؛ app/Providers/AppServiceProvider.php؛ app/Modules/Company/Models/DocumentSequence.php؛ tests/Database/TenancyTest.php؛ tests/Database/CompanySchemaTest.php؛ AGENTS.md وARCHITECTURE.md وTASKS.md. مراجع AGENTS/ARCHITECTURE في الحزمة المحفوظة متزامنة؛ نسخة TASKS هناك تاريخية ولا تحدد الحالة الحالية.

**الاختبارات السابقة:** لم تُحذف أو تُخفف Assertions/اختبارات P1-T01. تغييرات CompanySchemaTest تفتح سياق شركة موثوقًا حول العمليات التي باتت تتطلبه؛ جميع اختبارات القيود والعلاقات السابقة مستمرة وناجحة.

**الأمان والنطاق:** الاختبارات بقاعدة tawzee_test ومعاملات تتراجع تلقائيًا؛ لا migrate:fresh أو db:wipe أو migrations أو اختبارات مدمرة على التطوير. لم تُعرض أسرار، ولم تُشغّل PostgreSQL؛ بقاؤها مؤجلة لا يؤثر على إثبات MySQL. لا تغييرات واجهة تستدعي إعادة npm build. **توقف بعد P1-T02؛ التالي P1-T03 في دورة لاحقة فقط.**

### دليل P1-T03 — التسجيل الأساسي الآمن (2026-09-20)

**الحالة: Done على MariaDB 10.4.32.** لم يبدأ P1-T04 أو أي مهمة لاحقة. لم تُمح أدلة Phase 0 أو P1-T01/P1-T02 أعلاه. المصدر الحالي للحالة هو هذا الملف.

**التنفيذ ومعايير القبول:**
- Action `App\Modules\Identity\Actions\RegisterCompany`: للزائر فقط، يتحقق من قائمة مغلقة (اسم الشركة، الاسم، البريد، كلمة المرور وتأكيدها)، ويطبع البريد إلى lowercase. يرفض company_id والحالة والتفعيل والأدوار والصلاحيات والمؤسس وأرقام التسلسلات وأي حقول إضافية. البريد عالمي وفريد بقيد قاعدة البيانات القائم؛ كلمات المرور عبر hashed cast ولا تُعرض أو تُسجّل.
- DB::transaction واحدة: الشركة pending_setup، المستخدم المرتبط بالشركة، مرجع المؤسس، ثم invoice/opening_balance/receipt تبدأ من 1 داخل CurrentCompany::run للشركة المنشأة. فشل المستخدم أو التسلسل أو الاستثناء أو إلغاء الحفظ بإرجاع false يرجع الجميع. لا تعديل لمنطق Scope أو استثناءاته، ولا إنشاء مستخدم بلا شركة.
- ترحيل إضافي `2026_09_20_000400_add_company_founder`: founder_user_id غير قابل لـmass assignment، وFK مركب companies(id,founder_user_id) → users(company_id,id). المرجع لا يمنح صلاحيات؛ P2-T02 يستخدمه لإسناد Owner. يظل nullable للشركات السابقة ولترتيب الإنشاء، ولا يكتمل التسجيل الجديد دونه. لا اختيار تلقائي لمؤسس الشركات السابقة.
- الواجهة `/register`: Blade + Livewire + Tailwind، عربية RTL، تسميات وARIA وحالات تحميل/خطأ/نجاح. تتحقق من الضيف عند العرض وعند كل إرسال، وتقيد المحاولات إلى 5 في الدقيقة لكل IP، وتمسح كلمات المرور بعد المحاولة، وتحمي حالة النجاح بـLocked. لا دخول تلقائي أو Dashboard أو صفحات تجارية.
- إعادة الإرسال بالبريد نفسه تُرفض برسالة عربية عامة، دون إنشاء شركة ثانية أو إرجاع الحساب الموجود. قيد البريد هو الحماية النهائية؛ لا مفتاح Idempotency منفصل للعملية غير المالية. لا Roles/Permissions/Customers/Warehouses/Inventory جديدة.

**التحقق الفعلي النهائي (0 فشل):**
- `php scripts/inspect-database.php --testing` ثم الاتصال الفعلي والتحقق من هوية الحساب وصلاحياته: `tawzee_test` / `tawzee_test_app@127.0.0.1`. كل اختبارات قاعدة البيانات على هذا الاتصال المعزول؛ اختبارات عامة دون بيانات على SQLite memory وفق الإعداد السابق.
- `php artisan migrate --env=testing --database=mysql_testing --force`: ترحيل founder الإضافي نجح. بعد فحص اتصال التطوير `php scripts/inspect-database.php` نجح `php artisan migrate --database=mysql --force` على `tawzee_dev` / `tawzee_dev_app@127.0.0.1`. `migrate:status` أثبت 7 migrations مطبقة على كل قاعدة. لا إعادة إنشاء أو مسح أو rollback للترحيلات.
- `php vendor/bin/phpunit -c phpunit.mysql.xml --filter Registration`: **43 tests / 147 assertions**. نجاح/روابط/Hash/pending_setup، تحقق عربي وحقول ممنوعة، duplicate/replay، فشل قيود المستخدم والتسلسل، الاستثناءات عبر مراحل العملية، إلغاء Eloquent save، المؤسس من شركة أخرى، تنظيف السياق، تفويض Livewire، تحديد المعدل وحماية رسائل الخطأ وحالة النجاح.
- `composer test:mysql`: **113 tests / 290 assertions**؛ يشمل اختبارات عزل P1-T02 السابقة دون تعديلها.
- `composer test`: **23 passed / 41 assertions**.
- `composer lint`: Pint passed + PHP syntax **56 files passed**.
- `npm.cmd run build`: نجح Vite 7.3.6، **58 modules**. استُخدم npm.cmd لأن PowerShell منع npm.ps1؛ لم تتغير سياسة النظام.
- Chrome headless على خادم testing محلي: عرضا **375 و1440**، RTL وعربية وخط محلي، خمسة حقول بتسميات، لا overflow ولا أخطاء JavaScript أو موارد خارجية. فحص المتصفح عرض النموذج فقط؛ إرسال التسجيل اختُبر عبر Livewire/PHPUnit. الصور وأداة الفحص داخل storage/app/private غير المتتبع.

**دليل التزامن:** `RegistrationConcurrencyTest` يشغّل عمليتي PHP باتصالين مستقلين فعليين إلى MariaDB. يجبر كليهما على تجاوز Validation والوصول إلى User::creating، ثم يطلقهما معًا. النتيجة: **نجاح واحد ورفض بريد مكرر واحد**، وشركة واحدة بمؤسسها وثلاثة تسلسلات. لم يكن ذلك محاكاة أو طلبين متتابعين، لكنه اختبار Action/DB وليس سباق HTTP عبر متصفح. كل عامل يعيد حواجز أمان قاعدة الاختبار؛ ينظف الاختبار سجلاته فقط بمعرف UUID وشروط شركة محددة، دون DELETE غير مشروط أو TRUNCATE أو DROP.

**ملاحظة مراجعة:** كشفت أربعة اختبارات جديدة لإلغاء أحداث Eloquent عن ثلاثة إخفاقات قبل الإصلاح؛ أُصلح السبب بإثبات حفظ كل خطوة وإطلاق استثناء عند الإلغاء. أُعيدت الاختبارات المرتبطة والمجموعتان الكاملتان وlint، والنتائج النهائية أعلاه؛ لم تُضعف اختبارات سابقة.

**الملفات الجديدة:**
- app/Modules/Identity/Actions/RegisterCompany.php
- app/Livewire/RegisterCompany.php
- database/migrations/2026_09_20_000400_add_company_founder.php
- lang/ar/registration.php
- resources/views/registration.blade.php
- resources/views/livewire/register-company.blade.php
- tests/Database/RegistrationTest.php
- tests/Feature/RegistrationPageTest.php
- tests/MySql/RegistrationConcurrencyTest.php
- tests/Support/registration-worker.php

**الملفات المعدلة:** app/Modules/Company/Models/Company.php، app/Modules/Company/Models/DocumentSequence.php، routes/web.php، resources/views/layouts/app.blade.php، ARCHITECTURE.md (ونسخته tawzee-ai-foundation/ARCHITECTURE.md)، TASKS.md. لم تعدّل .env أو حزم Composer/npm أو اختبارات العزل السابقة. المستودع الحالي بلا commit أساس؛ الملفات كانت untracked مسبقًا، لذلك روجع المحتوى مع نسخة ما قبل المهمة في storage/app/private دون إضافة أسرار إلى Git.

**التبعيات والحدود:** P1-T04 للمصادقة والدخول بعد التسجيل؛ P1-T05 لسياق Middleware وتكامل المسارات المحمية ومنع استعمال شركة غير جاهزة في الميزات التجارية؛ P2-T02 لإسناد Owner من مرجع المؤسس؛ P4-T08 للبيانات التابعة والتفعيل فقط بعد اكتمالها. لا صلاحيات Owner مؤقتة ولا Gate::before. PostgreSQL لم يُشغّل وما زال تحققها مستقلًا في P8-T08. لم يُختبر down للترحيل احترامًا لمنع أوامر قاعدة البيانات المدمرة. لا عائق أساسي متبقٍ ضمن P1-T03؛ الشركات القديمة بلا مؤسس تحتاج معالجة صريحة عند التهيئة اللاحقة. **توقف بعد P1-T03؛ المهمة التالية P1-T04 لم تبدأ.**

### دليل P1-T04 — المصادقة الآمنة (2026-09-20)

**الحالة: Done على MariaDB 10.4.32؛ P1-T05 باقية Not Started.** لا ترحيلات جديدة ولا حزم أو أدوار أو صلاحيات أو Dashboard تجاري. محفوظة أدلة المهام السابقة وحالاتها.

**التنفيذ:**
- Laravel SessionGuard للدخول بالبريد المطبع وكلمة المرور مع is_active=true، رسالة موحدة للخطأ/الحساب الغائب/المعطل، وحدود معدل لكل بريد+IP ولـIP عبر عمليات المصادقة الثلاث. تجديد الجلسة وCSRF عند النجاح، وجهة ثابتة إلى /pending-setup دون اعتماد intended URL أو منح Owner.
- الخروج POST فقط مع web/auth وCSRF؛ إنهاء Auth وإبطال الجلسة وتجديد CSRF ثم الصفحة العامة. /pending-setup ترفض الضيف وتوضح عدم جاهزية الشركة؛ تفحص تعطيل المستخدم مجددًا وتطبق auth.session لإبطال الجلسة القديمة بعد تغير كلمة المرور.
- Laravel Password Broker بالجدول الموجود: رموز hashed، صلاحية 60 دقيقة، مهلة إصدار 60 ثانية، إبطال بعد الاستخدام واستبدال عند الإصدار الجديد. التعيين داخل transaction وقفل مستخدم، ولا يغير company_id/is_active/الاسم/البريد أو يمنح صلاحيات؛ يدور remember_token ولا يسجل الدخول تلقائيًا. المعطل لا يتجاوز التعطيل حتى برمز سابق.
- رسائل الاستعادة لا تكشف وجود الحساب أو تعطيله أو حالة throttling الداخلية للـBroker. Mail::fake لكل اختبارات البريد؛ لا إرسال حقيقي. تشغيل SMTP يحتاج إعدادًا وتفعيل PASSWORD_RESET_MAIL_ENABLED كما في README؛ الافتراضي false ولا يستخدم log mailer.
- واجهات عربية RTL متناسقة مع التسجيل، مع validation وloading وlabels/ARIA وno-store وno-referrer. تسجيل الشركة بقي كما هو منطقيًا؛ تعديل رسالة النجاح وإضافة رابط الدخول فقط.

**النتائج الفعلية النهائية — صفر فشل:**
- `php scripts/inspect-database.php --testing`: اتصال وهوية وصلاحيات الاختبار مؤكدة، tawzee_test / tawzee_test_app@127.0.0.1؛ MariaDB 10.4.32. لم تستخدم قاعدة التطوير لاختبارات البيانات.
- `php vendor/bin/phpunit -c phpunit.mysql.xml --filter Authentication`: **32 tests / 207 assertions** (الفحص الموجه يضم أيضًا اختبار المصادقة السابق المطابق للاسم).
- `composer test:mysql`: **144 tests / 493 assertions**، يشمل التسجيل وتزامنه وعزل P1-T02 دون تعديل اختباراتهما.
- `composer test`: **28 passed / 68 assertions**؛ الاختبارات العامة على SQLite memory وفق الإعداد السابق، وليست إثباتًا لقاعدة MariaDB بذاتها.
- `composer lint`: **Pint passed؛ PHP syntax 69 files passed**.
- `npm.cmd run build`: **Vite 7.3.6، 58 modules، نجاح**.
- HTTP middleware فعلي في الاختبارات: غياب CSRF يرفض POST logout وLivewire login بـ419؛ وجوده ينجح. أُلغي فقط إعفاء CSRF التلقائي لبيئة PHPUnit أثناء هذين الاختبارين، مع إبقاء اتصال tawzee_test المحروس. أُثبت تجديد المعرف وإتلاف بيانات Session ID القديم بعد الدخول والخروج.
- Mail::fake: إرسال الرسالة للحساب النشط، رابط بأصل APP_URL موثوق، تخزين hash للرمز، الرمز الخاطئ والمنتهي والمستهلك والمستبدل/المخصّص لحساب آخر، الحساب المعطل بعد إصدار الرمز، سياسة كلمة المرور والتأكيد، وإلغاء الحفظ دون استهلاك الرمز. **SMTP الحقيقي لم يُرسل أو يُختبر.**
- Chrome headless: صفحات login/forgot-password/reset-password بعرضي **375 و1440**، عربية RTL وتسميات سليمة ودون overflow أو JavaScript errors. تدفق متصفح فعلي: دخول → pending_setup → خروج → رفض الضيف، ثم دخول وتعطيل الحساب من اتصال اختبار مستقل → إنهاء جلسته عند فتح صفحة الانتظار. نُظّفت بيانات UUID المؤقتة فقط داخل tawzee_test؛ فحص لاحق لم يجد أي fixture auth-browser باقٍ. فحص المتصفح للاستعادة كان للعرض؛ عملية الاستعادة نفسها مثبتة بـPHPUnit وMail::fake.

**ملاحظات المراجعة والإصلاح:** واجهت الاختبارات أولًا فرق Session store في Livewire::test (الذي يعطل Middleware داخليًا)، فاستُخدم session() القياسي. عولج بقاء error bag بعد نجاح محاولة لاحقة. صححت الاختبارات تحميل defaults من DB ومحاكاة كوكي الجلسة مع withCredentials لطلبات JSON كي تفحص إتلاف الجلسة نفسها؛ لم تُخفّف شروط الإبطال أو CSRF. فشل أول سكربت متصفح لتعارض اسم متغير داخله، ثم صُحّح ونُظّفت بياناته وأُعيد بنجاح. لا تغييرات على اختبارات التسجيل والعزل السابقة.

**الملفات الجديدة:**
- app/Modules/Identity/Actions/{LoginUser,LogoutUser,SendPasswordResetLink,ResetUserPassword}.php
- app/Modules/Identity/Support/AuthenticationInput.php
- app/Modules/Identity/Http/Controllers/SessionController.php
- app/Modules/Identity/Mail/PasswordResetLink.php
- app/Livewire/Auth/{Login,ForgotPassword,ResetPassword}.php
- lang/ar/authentication.php
- resources/views/auth/{login,forgot-password,reset-password,pending}.blade.php
- resources/views/livewire/auth/{login,forgot-password,reset-password}.blade.php
- resources/views/components/{auth-field,auth-submit}.blade.php
- resources/views/mail/password-reset.blade.php
- tests/Database/AuthenticationTest.php، tests/Feature/AuthenticationPagesTest.php

**الملفات المعدلة:** config/auth.php، routes/web.php، resources/views/layouts/app.blade.php، lang/ar/registration.php، resources/views/livewire/register-company.blade.php، .env.example (علم تفعيل false فقط)، README.md، ARCHITECTURE.md ونسخته tawzee-ai-foundation/ARCHITECTURE.md، TASKS.md. لم تُعدل .env/.env.testing الفعليتان أو User أو Tenancy أو RegisterCompany Action أو migrations. صور المتصفح وأدواته ونسخ المراجعة ضمن storage/app/private وغير متتبعة.

**الحدود والتبعيات:** حماية المستخدم المعطل والجلسة القديمة مثبتة على الصفحة المحمية الحالية؛ تعميمها على المسارات المستقبلية وLivewire وربط Company Middleware مطلوب في P1-T05 ولم يُنفذ هنا. لا تفعيل تجاري قبل P4-T08 ولا Owner قبل P2-T02. البريد التشغيلي يحتاج إعداد SMTP وتفويض إرسال واختبار تسليم مستقل؛ راجع README، وامنع تسجيل رموز URL وطلبات Livewire في بنية الاستضافة. PostgreSQL لم يُختبر وبقي مؤجلًا إلى P8-T08. لا عائق أساسي متبقٍ ضمن P1-T04. **توقف هنا؛ المهمة التالية P1-T05 لم تبدأ.**

### دليل P1-T05 — Middleware الشركة والمسارات المحمية (2026-09-20)

**الحالة: Done على MariaDB 10.4.32.** P1-T06 وP2 لم تبدأ. لا جداول أو migrations أو حزم أو واجهات أعمال جديدة، ولم تتغير حالات المهام الأخرى أو تُمح أدلتها.

**التصميم والتنفيذ:**
- SetCurrentCompany في مجموعة web يعيد تحميل المستخدم من DB بمعرف Auth الأصلي ثم الشركة من company_id الموثوق؛ لا اختيار من الطلب أو علاقة Eloquent مخبأة. يرفض المستخدم المعطل/المفقود والشركة غير القابلة للحل، وينهي الجلسة مع CSRF جديد دون حفظ نموذج الهوية القديم. لا Scope على User.
- CurrentCompany::forRequest يغلف الطلب الفعلي، يزيل أي سياق سابق ويغلقه في finally حتى بعد exception. يبقى scoped وليس Singleton؛ run الداخلي للتسجيل محفوظ. لا fallback إلى Auth بعد إغلاق سياق HTTP، ولا تسرب بين طلبين في التطبيق نفسه.
- RequireCompany باسم tenant يرفض company_id في URL/query/header/body/cookie وLivewire updates، بما فيه القيمة المطابقة للشركة الصحيحة. company.ready مستقل يفحص status=active ولا يمنح صلاحية؛ pending_setup لا تمر منه.
- التسجيل في bootstrap/app.php يضع السياق بعد StartSession وقبل Binding، ويعمم AuthenticateSession على web. AppServiceProvider يسجل RequireCompany وEnsureCompanyReady وAuthenticateSession وguest redirect ضمن Livewire Persistent Middleware. سياق الشركة يغلف طلب update الحقيقي؛ لا يُنظف مبكرًا في pipeline الإعادة.
- أزيل الفحص المحلي المكرر من SessionController لصالح الحماية العامة. صفحات الضيف تستخدم guest وتعيد المصادق عليه إلى pending-setup. صفحة الانتظار تحت auth + tenant، والخروج POST/auth/CSRF. الصفحة العامة والتسجيل بلا اشتراط مصادقة، مع بقاء تسجيل الشركة للضيف كما كان. لا مسار إنتاجي يكشف DocumentSequence.

**النتائج الفعلية النهائية — صفر فشل:**
- `php scripts/inspect-database.php --testing`: MariaDB **10.4.32**، قاعدة **tawzee_test** وحساب **tawzee_test_app@127.0.0.1**؛ هوية الاتصال والصلاحيات وحواجز الاختبار مؤكدة. كل اختبارات البيانات داخل قاعدة الاختبار فقط ومع DatabaseTransactions.
- `php vendor/bin/phpunit -c phpunit.mysql.xml --filter TenantMiddlewareTest`: **29 tests / 99 assertions**.
- `composer test:mysql`: **173 tests / 592 assertions**، تشمل التسجيل وتزامنه والمصادقة والاستعادة والخروج والعزل السابق، دون تعديل اختبارات المهام السابقة.
- `composer test`: **28 passed / 68 assertions**؛ الاختبارات العامة على SQLite memory وفق الإعداد السابق.
- `composer lint`: **Pint passed؛ PHP syntax 75 files passed**.
- `php artisan route:list -vv --except-vendor` و`php artisan route:list --path=livewire -vv`: تأكيد ترتيب middleware، guest للمصادقة، auth/tenant للانتظار، POST/auth للخروج، web + SetCurrentCompany + AuthenticateSession على endpoint Livewire الفعلي. لا مسارات /_tenant/* في إنتاج.
- لا تعديل لملفات واجهة الإنتاج؛ لم يُشغّل npm build أو متصفح جديد. ملف Blade المضاف fixture اختباري فقط. فُحص Livewire عبر طلبات HTTP حقيقية وsnapshots أصلية، لا عبر Livewire::test المعطل للـMiddleware.

**تغطية الأمان:** الضيف، المستخدم النشط، مستخدم عُطّل بجلسة قديمة حتى على صفحة عامة، هوية محذوفة، شركة غير قابلة للحل، company_id غير صالح محاكى عند hydration، تجاهل الحقول والعلاقات غير المحفوظة، رفض انتحال الشركة عبر المصادر الستة بالقيمة الصحيحة والأجنبية، Binding 404 لسجل B ونجاح سجل A، الانتقال A→B وتنظيف السياق بعد HTTP/Livewire exception، Livewire Action يكتب سجل A ولا يكتب B، رفض snapshot بعد الخروج/تعطيل المستخدم، رفض تحديث public company_id قبل Action، إعادة فحص الجاهزية بعد العرض، ورفض إعادة snapshot A للكتابة بعد تبديل الهوية إلى B. القيود الحقيقية لم تُعطّل لصنع بيانات فاسدة؛ حالة company_id غير الصالح محاكاة DB hydration، وليست صفًا يتجاوز FK.

**إصلاح أثبتته الاختبارات:** عند رفض هوية قديمة، logout العادي قد يدور remember_token ويحفظ النموذج القديم. اختباران إضافيان أظهرا إعادة إدراج هوية محذوفة وكتابة company_id غير محفوظ أثناء الإنهاء. استُخدم logoutCurrentDevice + invalidate + regenerateToken في مسار الرفض فقط، فأُثبت عدم تغيير الربط أو إعادة المستخدم. LogoutUser العادي لم يتغير. أُعيد الفحص الموجه والمجموعتان وlint بعد الإصلاح، والنتائج النهائية أعلاه.

**الملفات الجديدة:**
- app/Http/Middleware/SetCurrentCompany.php
- app/Http/Middleware/RequireCompany.php
- app/Http/Middleware/EnsureCompanyReady.php
- tests/Database/TenantMiddlewareTest.php
- tests/Fixtures/TenantProbe.php
- tests/Fixtures/views/tenant-test-probe.blade.php

**الملفات المعدلة:** app/Support/Tenancy/CurrentCompany.php، app/Providers/AppServiceProvider.php، app/Modules/Identity/Http/Controllers/SessionController.php، bootstrap/app.php، routes/web.php، ARCHITECTURE.md ونسخته tawzee-ai-foundation/ARCHITECTURE.md، README.md، TASKS.md. لا تغييرات على .env أو بيانات الاتصال أو CompanyScope/BelongsToCompany/User/Company أو LoginUser/LogoutUser أو Actions التسجيل والاستعادة أو migrations. نسخة المراجعة السابقة ضمن storage/app/private غير المتتبع؛ المستودع ما زال بلا commit أساس والملفات untracked كما سبق.

**الحدود المؤجلة:** P2 للأدوار وPolicies وتفويض كل مورد/Action؛ founder ليس إذنًا ولا يوجد Gate::before. P4-T08 للتهيئة والتفعيل الفعلي، والحارس لا يقوم بالتفعيل. على المسارات التجارية المستقبلية استخدام auth + tenant + company.ready وإضافة Policies. لا ادعاء بفحص Octane/Queue worker طويل العمر أو كل الموارد المستقبلية؛ اختُبرت طلبات متتابعة في نفس تطبيق الاختبار. PostgreSQL لم تُشغّل وتبقى بوابة P8-T08. لا أوامر قواعد مدمرة، ولا أسرار معروضة، ولا عائق أساسي متبقٍ ضمن المهمة. **التوقف بعد P1-T05؛ التالي P1-T06 لم يبدأ.**

### دليل P1-T06 — مساعد اختبارات العزل القابل لإعادة الاستخدام (2026-09-20)

**الحالة: Done على MariaDB 10.4.32.** المساعد داخل tests فقط؛ لم يبدأ P1-T07 أو Phase 2، ولا تغيير في منطق حماية التطبيق أو Schema أو إعداد قاعدة البيانات.

**التنفيذ والواجهة:** Trait `InteractsWithTenantIsolation` ينشئ pair بمستخدمين وشركتين وموردين عبر Closure من المستدعي، ويتحقق من وجودهما وربطهما الفعلي في DB. withinTenant يفتح CurrentCompany::run بهوية موثوقة محدودة ويعيد هوية المستدعي في finally. storedTenantResource يقرأ بسياق صاحب المورد وشرط company_id. لا أسماء Models/Routes تجارية مستقبلية ولا framework أو حزمة إضافية. أسماء وطريقة استعمال assertTenantReadIsolation/UpdateIsolation/DeleteIsolation/HttpIsolation/CreationRequiresContext/InjectionRejected/ContextClosesOnException موثقة بأمثلة في README.

**منع النتائج الإيجابية الكاذبة:**
- الوصول الصحيح إلى A شرط سابق لمنع B؛ كلا الموردين مثبت الوجود في شركتهما. التحديث يجب أن يغير القيم المتوقعة على A والحذف يجب أن يزيل A فعلًا، ثم تُقرأ B بهوية صاحبها للتحقق من ثبات كامل الصف وبقائه.
- HTTP يتطلب 200 وهوية A الصحيحة قبل 404 لـB؛ لا يقبل هوية مورد داخل استجابة المنع، ويتحقق من عدم تغير B. الإنشاء الطبيعي مثبت قبل رفض السياق المفقود/الملكية المحقونة، مع مقارنة صفوف المورد في الشركتين بعد الطلب المرفوض.
- حالات عدائية مستقلة أثبتت فشل المساعد عند قراءة B عبر Model تجريبي بلا Scope، وتعديل B أو حذفها فعليًا مع إرجاع صفر مضلل، وإنشاء دون سياق، وكتابة company_id محقون ثم رمي AuthorizationException. كذلك يرفض مورد B غير موجود، وعدم قراءة أي مورد، وتعديل/حذف لا يعمل حتى على A، و404 عامًا، ومعرف استجابة خاطئًا، و404 يسرّب هوية المورد. تحدد هذه الاختبارات AssertionFailedError ورسالة موضع الفشل المتوقع؛ لا يُعد أي استثناء نجاحًا. الاستثناء غير الأمني يُعاد كما هو.
- Fixture غير المحمي يخص الاختبار فقط، على document_sequences الموجودة في tawzee_test؛ لا removal لـGlobal Scopes من نموذج الإنتاج. محاولات الكتابة العدائية تستهدف معرف/شركة fixture محددين داخل DatabaseTransactions؛ لا DELETE غير مشروط أو TRUNCATE أو reset.

**التكامل:** أعيد استعمال المساعد في setUp واختبار القراءة القائم في TenancyTest على DocumentSequence؛ بقيت جميع Assertions السابقة كما هي وأضيفت ضوابط وجود ووصول. اختبار مستقل يغطي Eloquent والكتابة والحذف والسياق. اختبار HTTP يستخدم Route Model Binding بمسار اختبار محمي فقط؛ واختبار Livewire يعيد استخدام TenantProbe القائم عبر طلب update HTTP حقيقي ويثبت تغير A وثبات B. TenantMiddlewareTest وCompanySchemaTest وDatabaseSafety لم تتغير.

**النتائج الفعلية النهائية — صفر فشل:**
- `php scripts/inspect-database.php --testing`: **tawzee_test**، حساب tawzee_test_app@127.0.0.1، **MariaDB 10.4.32**؛ هوية الاتصال والصلاحيات وحواجز الاختبار مؤكدة.
- `php vendor/bin/phpunit -c phpunit.mysql.xml --filter TenantIsolationHelperTest`: **18 tests / 458 assertions**، بما فيها الاختبارات التي تتوقع فشل المساعد عند التسرب الحقيقي.
- `php vendor/bin/phpunit -c phpunit.mysql.xml --filter 'TenancyTest|TenantMiddlewareTest|CompanySchemaTest|DatabaseSafetyTest'`: **92 tests / 526 assertions**.
- `composer test:mysql`: **191 tests / 1359 assertions**.
- `composer test`: **28 passed / 68 assertions**؛ الاختبارات العامة وفق إعداد SQLite memory السابق، ولا عمليات بيانات عليها من المساعد الجديد.
- `composer lint`: **Pint passed؛ PHP syntax 78 files passed**.
- مراجعة الفرق مع نسخة ما قبل المهمة: جميع Assertions القديمة في TenancyTest محفوظة، وTenantMiddlewareTest لم يتغير. زيادة التأكيدات تشمل فحص fixtures في كل اختبار؛ عددها وحده ليس دليل الأمان، ودليل اكتشاف التسرب موضح أعلاه.

**الملفات الجديدة:** tests/Concerns/InteractsWithTenantIsolation.php، tests/Database/TenantIsolationHelperTest.php، tests/Fixtures/UnprotectedTenantRecord.php.
**الملفات المعدلة:** tests/Database/TenancyTest.php، README.md (طريقة الاستخدام وحدودها)، TASKS.md. لا ملفات تطبيق أو مسارات إنتاج أو واجهات أو migrations أو .env أو إعداد PostgreSQL معدلة. لم يلزم npm build أو فحص متصفح جديد؛ تكامل Livewire فُحص بطلبات HTTP في PHPUnit.

**الحدود:** الإثبات على DocumentSequence والـfixtures المنفذة، لا على كل الموارد المستقبلية أو Workers. يجب على مستعمل المساعد إنشاء موردين في حالتي أعمال متكافئتين واختيار assertions تلائم semantics المورد، وإضافة Policies/أدوار صحيحة بعد تنفيذ P2؛ المساعد ليس حماية تطبيقية. PostgreSQL لم يُشغّل وبقي مؤجلًا إلى P8-T08. لا عائق متبقٍ ضمن P1-T06. **التوقف بعد P1-T06؛ المهمة التالية P1-T07 ولم تبدأ.**

### دليل P1-T07 — سجل النشاط الآمن والمعزول (2026-09-26)

**الحالة: Done على MariaDB 10.4.32.** لم يبدأ P2-T01 أو أي عمل من Phase 2، ولم تُضف واجهة لسجل النشاط.

**Schema والنموذج:** migration إضافية تنشئ `activity_logs` مع `company_id`، فاعل nullable مقيد بالشركة، action، polymorphic subject، JSON properties متوافق مع MariaDB/PostgreSQL، IP وtimestamps، مع فهارس الشركة/الإجراء/الفاعل/الموضوع. `ActivityLog` يستخدم `BelongsToCompany` وعلاقاته actor/subject/company، ويحظر update/delete العادي والهادئ وعمليات Builder الجماعية؛ هذا ضمان تطبيق Eloquent وليس منع SQL مباشر أو DBA.

**الخدمة والحماية:** `RecordActivity::record(action, subject, properties)` يستمد الشركة من `CurrentCompany` والفاعل من Auth فقط، ويرفض actor أو subject عابر الشركة ولا يقبل company_id/actor من المستدعي. properties allow-listed من Action المستدعية وليست Request dump، ثم تُنقّح recursively لمفاتيح password/token/session/cookie/Authorization/CSRF/APP_KEY/DB/SMTP/API secrets مع اختبارات تمنع بقاء القيم الأصلية.

**المعاملات والتكامل:** السجل يكتب داخل معاملة الأعمال نفسها. أضيف حدث `company.registered` فقط داخل معاملة P1-T03 بعد إنشاء التسلسلات؛ الفاعل nullable لأنه تسجيل نظامي قبل الدخول. اختبار failure في إنشاء ActivityLog يثبت rollback للشركة والمستخدم والتسلسلات والسجل، واختبار مستقل يثبت commit/rollback المتزامن لسجل وDocumentSequence.

**العزل والاختبارات:** أعيد استعمال `InteractsWithTenantIsolation` لإثبات positive control ثم إخفاء سجل B عن A عبر Eloquent وRoute Model Binding. غطت الاختبارات actor النظامي، action/subject/properties، nested redaction، رفض cross-company، عدم وجود مدخلات caller-owned للفاعل/الشركة، 11 مسار mutation محظوراً، وtransaction semantics. تعديل teardown في اختبار التسجيل المتزامن يحذف فقط audit fixture للشركة ذات UUID الاختباري عبر DB raw قبل حذف الشركة؛ لا يغير حماية الإنتاج.

**النتائج الفعلية النهائية — صفر فشل:**
- `php scripts/inspect-database.php --testing`: **tawzee_test**، حساب **tawzee_test_app@127.0.0.1**، **MariaDB 10.4.32**؛ حواجز الهوية والصلاحيات مؤكدة.
- migration `2026_09_20_000500_create_activity_logs_table`: **Ran** على `tawzee_test` عبر `mysql_testing` وعلى `tawzee_dev` عبر `mysql` بعد أن أكد `php scripts/inspect-database.php` اتصال MariaDB 10.4.32 وهوية `tawzee_dev` / `tawzee_dev_app@127.0.0.1`. أكد `php artisan migrate:status --database=mysql` أنها في batch 4، ونجح RegisterCompany rollback probe على التطوير دون ترك شركة أو مستخدم تجريبي دائم. لم تُستخدم migrate:fresh/reset/refresh أو db:wipe أو rollback أو truncate.
- `php vendor/bin/phpunit -c phpunit.mysql.xml --filter ActivityLogTest --testdox`: **19 tests / 65 assertions**.
- `composer test:mysql`: **210 tests / 1424 assertions**.
- `composer test`: **28 passed / 68 assertions**.
- `composer lint`: **Pint passed؛ PHP syntax 83 files passed**.

**الملفات الجديدة:** migration activity_logs؛ `app/Modules/Access/Models/ActivityLog.php`؛ `app/Modules/Access/Database/ActivityLogBuilder.php`؛ `app/Modules/Access/Actions/RecordActivity.php`؛ `tests/Database/ActivityLogTest.php`.

**الملفات المعدلة:** RegisterCompany Action لتكامل واحد داخل transaction؛ علاقات User/Company؛ teardown الاختبار المتزامن؛ ARCHITECTURE.md؛ TASKS.md. لا `.env` أو credentials أو UI أو routes إنتاج أو packages أو PostgreSQL معدلة. **Phase 1 Completed on MariaDB. التالي P2-T01 ولم يبدأ؛ PostgreSQL مؤجل إلى P8-T08.**

### إغلاق P2-T01 — حزمة الأدوار والصلاحيات المعزولة (2026-09-26)

**الحالة: Done على MariaDB 10.4.32.** ثُبتت `spatie/laravel-permission` 6.25.0 فقط دون ترقية Laravel أو PHP أو أي حزمة رئيسية. فُعّلت Teams باستخدام `company_id` وربط Team Resolver بـ `CurrentCompany` الموثوق، مع Role خاضع لـ `CompanyScope` وحراسة تمنع إسناد مستخدم أو دور عبر شركة أخرى. لم تُنشأ أدوار افتراضية أو Enum أو Seeder، ولم يبدأ P2-T02.

**قاعدة البيانات:** الترحيل `2026_09_25_235738_create_permission_tables` أصبح Ran على `tawzee_test` أولًا ثم `tawzee_dev` بعد تحقق الهوية المستقل لكل اتصال. جداول الربط وroles تحمل `company_id` مع مفاتيح خارجية إلى companies. لم تستخدم أوامر fresh/reset/refresh/wipe/rollback/truncate.

**الاختبارات:** `PermissionTeamsTest` يغطي القراءة الإيجابية داخل الشركة، منع رؤية دور شركة أخرى، نجاح الإسناد الصحيح، رفض الإسناد المتقاطع في الاتجاهين، منع override لمعرف Team، واستعادة السياق بعد الاستثناء. `composer test:mysql`: **213 tests / 1434 assertions**. `composer test`: **28 tests / 68 assertions**. P2-T02 بقيت Not Started.

### إغلاق P2-T02 — مصفوفة الصلاحيات والأدوار الافتراضية (2026-09-26)

**الحالة: Done على MariaDB 10.4.32.** أضيف `Access\Enums\Permission` بالقيم التسع عشرة في مصفوفة PROJECT فقط، و`DefaultRole` للأدوار الستة حرفيًا: owner, admin, sales, warehouse, accountant, viewer. يطبق `InitializeCompanyRoles` المصفوفة كاملة عبر Spatie، وينشئ Role داخل `company_id` الحالي، ويزامن الصلاحيات بحيث تزال أي صلاحية غير مطلوبة.

**التهيئة والمؤسس:** الـAction يعمل داخل `CurrentCompany::run` ومعاملة قاعدة بيانات، وهو idempotent. يسند owner حصريًا إلى `founder_user_id` بعد التحقق من انتمائه للشركة. دُمج داخل معاملة `RegisterCompany` بعد التسلسلات وقبل سجل `company.registered`؛ أي فشل في إنشاء الأدوار يعيد الشركة والمستخدم والتسلسلات والصلاحيات والأدوار والإسنادات والسجل معًا. تبقى الشركة `pending_setup`. الشركات القديمة تُهيأ باستدعاء الـAction صراحة؛ إن غاب founder تُنشأ الأدوار دون تخمين مستخدم أو إسناد owner، ولا يوجد bulk backfill.

**العزل والاختبارات:** اختبار Data-driven مستقل يثبت كل صلاحية متوقعة وغير متوقعة لكل Role، 19 Permission بالضبط، ستة Roles بالضبط، تكرار التهيئة بلا تكرار، شركتين بنفس الأسماء دون تسرب، owner للمؤسس، شركة قديمة، وrollback عند الفشل. لم يضف ActivityLog جديد لأن الحدث التشغيلي المطلوب ما زال `company.registered` داخل المعاملة ولا حاجة إلى Permission dump.

- `php vendor/bin/phpunit -c phpunit.mysql.xml --filter CompanyRolesTest --testdox`: **4 tests / 31 assertions**.
- regressions للتسجيل وP2-T01 وسجل النشاط: **62 tests / 204 assertions**.
- `composer test:mysql`: **217 tests / 1465 assertions**.
- `composer test`: **28 tests / 68 assertions**.
- `composer lint`: **Pint passed؛ PHP syntax 92 files passed**.

لا migration جديدة، ولا PostgreSQL، ولا UI، ولا Policies، ولا `Gate::before`. **المهمة التالية P2-T03 ولم تبدأ.**

### إغلاق P2-T03 — Policies ونمط authorize (2026-09-26)

**الحالة: Done على MariaDB 10.4.32.** أضيف `TenantPolicy` كأساس يفشل مغلقًا ويتحقق من `CurrentCompany` وشركة المستخدم والمورد ثم مفتاح `Permission` عبر Spatie، مع `DocumentSequencePolicy` كمثال حقيقي صغير مسجل صراحة في Gate. أثبت Controller وLivewire تجريبيان للاختبار فقط أن الاستدعاء الموحد هو `$this->authorize(...)`؛ لا route إنتاج ولا UI أو CRUD جديد، ولا فحص أسماء أدوار ولا `Gate::before`.

**العزل والسلوك:** نقص الصلاحية داخل الشركة يرجع 403، ومورد شركة أخرى محجوب بـCompanyScope ويرجع 404، وغياب السياق يفشل مغلقًا، وحقن `company_id` في query/header مرفوض. أصلح نموذج Permission بناء مخزن Spatie بجميع أدوار Teams بدل تخزين أدوار أول شركة فقط، مع تنظيف العلاقات المحملة عند تبديل/إغلاق `CurrentCompany`؛ تبقى استعلامات Role التطبيقية مقيدة بالشركة.

- `AuthorizationPolicyTest`: **5 tests / 24 assertions**.
- `PermissionTeamsTest`: **3 tests / 10 assertions**.
- `CompanyRolesTest`: **4 tests / 31 assertions**.
- regressions للمصادقة وTenant Middleware: **55 tests / 275 assertions**.
- `composer test:mysql`: **222 tests / 1489 assertions**.
- `composer test`: **28 tests / 68 assertions**.
- `composer lint`: **Pint passed؛ PHP syntax 98 files passed**.

لا migration، ولا تغيير schema أو بيانات، ولا PostgreSQL، ولا route أو UI إنتاج، ولم تبدأ إدارة المستخدمين. **المهمة التالية P2-T04 ولم تبدأ.**

### إغلاق P2-T04 — إدارة المستخدمين وواجهة التطبيق الإنتاجية (2026-09-26)

**الحالة: Done على MariaDB 10.4.32.** النطاق إدارة المستخدمين فقط مع App Shell وأساس واجهة قابل لإعادة الاستخدام؛ P2-T05 لم يبدأ. لا Company Settings أو وحدات تجارية أو روابط مستقبلية أو تغيير كلمات مرور/دعوات/حذف مستخدمين.

**الفحص الأولي:** شجرة العمل نظيفة؛ HEAD هو `ed4ed30` (P2-T03). قرئت المراجع والمهارتان والكود المطلوب. `composer.json` يطلب `~4.4.5` وcomposer.lock و`composer show livewire/livewire --format=json` يؤكدان **v4.4.5** المثبت. لم تتغير ملفات الحزم. MariaDB كانت متوقفة؛ فشل الاتصال الأول، ثم شغلت الخدمة المحلية القائمة. فحص الهوية بعد التشغيل: `tawzee_test` / `tawzee_test_app@127.0.0.1`، 17 جدولًا. baseline المجموعة القائمة: **222 tests / 1489 assertions**. `migrate:status --env=testing --database=mysql_testing` يثبت التسعة Ran دون تشغيل أي migration.

**ما نُفذ:**
- `/users` محمية ومقيدة بالشركة، قائمة بالعربية، بحث اسم/بريد، فلاتر دور وحالة، pagination خادمية 10/صفحة، إحصاءات المستخدمين فقط. Desktop table وMobile/Tablet cards، شارات الدور والحالة والمؤسس، empty/no-results منفصلتان. حالة «لا مستخدمين» دفاعية؛ وجود مستخدم مخوّل يجعلها غير قابلة للوصول في البيانات الطبيعية الحالية، وحالة no-results اختبرت فعليًا.
- Create/Edit في modal: الاسم والبريد والدور؛ كلمة المرور وتأكيدها عند الإنشاء فقط وفق السياسة القائمة. البريد مطبع وفريد عالميًا، والرسائل عربية. تفعيل/تعطيل بـis_active وتأكيد يوضح الأثر، دون حذف. النجاح يمسح النموذج وكلمة المرور؛ الخطأ يبقي المدخلات.
- App Shell جديد بSidebar وTopbar ودرج جوال ومعلومات الشركة والحساب، وخط محلي. أساس UI عملي: button/field/badge/modal/toast مع card/input/select/alert والحالات في CSS. Native dialog وEscape وfocus trap/restoration وfocus-visible؛ الحالات نصية لا ألوان فقط.
- UserPolicy مع Permission::UsersManage، وإعادة authorization/tenant/actor/target داخل Actions. قفل الشركة والهدف ومعاملة واحدة تضمن عدم الحالة الجزئية. User يبقى غير scoped للمصادقة، وجميع عمليات الإدارة عبر CompanyUsers المقيدة. foreign target => 404؛ permission denial => 403؛ foreign role => validation عربية.
- Founder/Owner محميان من تعطيل/تغيير دور الآخرين، مع السماح بالاسم/البريد وبإعادة التفعيل. التغيير الذاتي مسموح وفق عبارة «بواسطة غيره» مع التنبيه والتحويل عند فقد الوصول؛ لا قاعدة آخر مالك مضافة. مرجع المؤسس لا يمنح صلاحية. المصفوفة الدقيقة موثقة في ARCHITECTURE §18.
- `user.role_changed` فقط وفق FR-19 لإسناد/تغيير الدور، داخل transaction وبمعرفات أدوار فقط. فشل ActivityLog أو إلغاء حفظ User يسبب rollback. لا أسرار أو بيانات هوية في properties/رسالة الخطأ العامة.
- دراسة Odoo/Zoho الرسمية أفادت في فصل بيانات الحساب والدور، وتنظيم القائمة والفلاتر، والتعديل المركز والتعطيل القابل للعكس. التكييف مستقل عربي RTL؛ المراجع والتفاصيل في ARCHITECTURE §18.

**التحقق النهائي — صفر فشل:**

| الأمر | النتيجة |
|---|---|
| `php vendor/bin/phpunit -c phpunit.mysql.xml --filter UserManagementTest --testdox` | **46 tests / 223 assertions** |
| `php vendor/bin/phpunit -c phpunit.mysql.xml --filter 'PermissionTeamsTest\|CompanyRolesTest\|AuthorizationPolicyTest\|Authentication\|TenantMiddleware'` | **74 tests / 389 assertions** |
| `composer test:mysql` | **268 tests / 1712 assertions** |
| `composer test` | **28 passed / 68 assertions** |
| `composer lint` | **Pint passed؛ PHP syntax 110 files passed** |
| `npm.cmd run build` | **Vite 7.3.6؛ 58 modules؛ نجاح** |
| `git diff --check` | **نجاح**؛ تحذيرات تحويل CRLF إلى LF فقط |

**التغطية:** الأدوار الستة وسلوك 403، positive controls للشركتين، قائمة/بحث/فلاتر/pagination معزولة، منع target/role أجنبيين، company_id المزور في query/header/Livewire body، Locked IDs، snapshot قديم بعد تبديل الهوية أو نقل الهوية نفسها إلى شركة أخرى، إبطال صلاحية الفاعل المخزنة، تغيير دور الهدف بعد التأكيد، غياب السياق، منع المعطل من الدخول وجلساته القديمة، الحماية الذاتية/الغيرية للمؤسس ومالك غير المؤسس، update validation وكلمة المرور غير القابلة للتعديل، rollback، أخطاء عامة دون exception text، escaping وquery count لا ينمو مع الصفوف.

**فحص المتصفح الفعلي:** `node scripts/verify-user-management.mjs` عبر Chrome headless/CDP المحلي، بلا npm packages إضافية، وخادم Laravel testing على `127.0.0.1:8016` باستخدام mysql_testing. احتاج تشغيل Chrome خارج قيود البيئة بعد تعثر الاتصال به داخل العزل. Fixtures تستخدم UUID وحواجز DatabaseSafety الثلاثة؛ تنظف سجلات شركتها فقط داخل transaction. لم تُستخدم أوامر wipe/fresh/reset/refresh/rollback/truncate/drop ولا اختبارات على قاعدة التطوير.
- عروض **1440 / 768 / 375**: lang=ar وRTL، خط محلي، لا horizontal overflow، جدول Desktop وبطاقات Tablet/Mobile، Sidebar/Drawer، labels، Tab focus trap وfocus-visible وEscape وإعادة التركيز، validation عربية.
- تدفق متصفح فعلي: دخول → رابط إدارة المستخدمين → بحث/فلاتر/صفحات/no-results → إنشاء → تعديل → تأكيد تعطيل → إعادة تفعيل. فحص loading مع latency اصطناعية يثبت تعطيل الإرسال، وsuccess toast ظاهر. **لا أخطاء JavaScript/console**. error toast اختبر على Livewire/PHPUnit بفشل حقن حقيقي، ولم يفرض فشل قاعدة بيانات من المتصفح.
- راجعت صور Desktop وMobile وvalidation بصريًا. النتائج والصور في `storage/app/private/user-management-browser/` غير المتتبع: `results.json`, `users-1440.png`, `users-768.png`, `users-375.png`, `validation-*.png`, `navigation-*.png`, `empty-375.png`, `edit-375.png`, `confirm-disable-375.png`, `loading-375.png`.
- كشف المتصفح مسح error bag أثناء render؛ فصل Validator الفلاتر عن Livewire validation وأضاف اختبار HTTP يثبت HTML الأخطاء. كذلك أصلح حفظ زر الفتح قبل تعطيله لضمان استعادة التركيز. أعيد الفحص بنجاح ثم جميع أوامر التحقق أعلاه. لا ادعاء باختبار متصفحات أخرى أو PostgreSQL.

**الملفات الجديدة:**
- `app/Modules/Identity/Actions/{CreateUser,UpdateUser,ChangeUserStatus}.php`، `Policies/UserPolicy.php`، `Livewire/Users.php`، `Support/{CompanyUsers,UserInput,UserProtection,UserRoleAssignment}.php`.
- `lang/ar/users.php`، `resources/views/layouts/workspace.blade.php`، `components/ui/{button,field,badge,modal,toasts}.blade.php`، `components/workspace/navigation.blade.php`، `identity/{index,users,user-identity,user-actions,pagination}.blade.php`.
- `tests/Database/UserManagementTest.php`، `tests/Support/user-management-browser-fixture.php`، `scripts/verify-user-management.mjs`.

**الملفات المعدلة:** `app/Providers/AppServiceProvider.php` (تسجيل Policy)، `routes/web.php`، `resources/css/app.css`، `resources/views/auth/pending.blade.php` (رابط مخوّل)، `ARCHITECTURE.md`، `TASKS.md`. لا تعديل لأي اختبار سابق، ولا `.env` أو Schema أو packages. لا commit/push أُجري. **المهمة التالية P2-T05 — إعدادات الشركة، لم تبدأ.**
