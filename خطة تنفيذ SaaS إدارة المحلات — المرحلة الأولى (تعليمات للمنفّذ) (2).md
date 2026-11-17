# خطة تنفيذ SaaS إدارة المحلات — المرحلة الأولى (تعليمات للمنفّذ)

هذا المستند تعليمات لتنفيذ **المرحلة الأولى فقط** من SaaS إدارة المحلات: بنية multi-tenancy تعتمد على الـ subdomains (مع دعم الدومين المخصص في التصميم من اليوم الأول)، ونظام الاشتراكات والميزات، ولوحة Super Admin. اقرأه كاملًا قبل كتابة أي كود، وما لا يذكره المستند اسأل عنه ولا تخمّنه.

ما هو مكتوب عليه **(مقترح)** لم يؤكده صاحب المشروع بعد: نفّذه كما هو ما لم يُعدَّل. وأي بند تحت «نقاط مفتوحة» لا تحسمه بنفسك.

## نبذة عن المشروع (Brief)

**الفكرة:** منصة SaaS لإدارة المحلات التجارية (مخزون وفواتير)، يبيعها صاحب المشروع لعدة محلات. كل محل (عميل) يدخل على نظامه من رابط خاص به، وبياناته معزولة تمامًا عن باقي العملاء. لكن الكود والنشر واحد: أي تعديل أو إصلاح يصل لكل العملاء مرة واحدة.

**كيف يدخل العميل:** من subdomain مثل `shop1.app.example.com` في البداية، ومن دومين مخصص له لاحقًا. التصميم يدعم الاثنين من أول يوم.

**النموذج التجاري:** اشتراكات. لكل باقة مجموعة ميزات وحدود، ويستطيع صاحب المشروع فتح أو قفل ميزة لعميل بعينه أو إعطاءه سعرًا خاصًا. الدفع يدوي حاليًا: يفعّل صاحب المشروع الاشتراك من لوحته. وعند انتهاء الاشتراك يحدد هو لكل ميزة: تبقى مفتوحة أو للقراءة فقط أو تُقفل، حتى لا يُحبس العميل عن بياناته.

**من يستخدم النظام:**

- **Super Admin:** صاحب المنصة وفريقه، عبر لوحة Filament منفصلة.
- **مستخدمو العميل:** كل مستخدم تابع لمحل واحد، بأدوار وصلاحيات يخصصها صاحب المحل بنفسه (`spatie/laravel-permission`)، ويمكن أن يحمل المستخدم أكثر من دور.

**المبادئ الحاكمة:**

1. كود واحد، وعزل بيانات صارم بـ `tenant_id`. تسريب بيانات بين عميلين هو أخطر عطل ممكن.
2. الكود يسأل عن «ميزة» ولا يسأل عن «باقة».
3. النظام مفتوح للتوسع: أنواع المستخدمين والميزات والباقات تُضاف من البيانات لا من تعديل الكود، قدر الإمكان.

**التقنية:** Laravel 12 وFilament v5 وPHP `^8.2` وMySQL، مع `stancl/tenancy` لتعدد العملاء و`spatie/laravel-permission` للصلاحيات.

**خريطة المراحل** (ترتيب ما بعد الأولى مقترح):

| المرحلة | المحتوى | الحالة |
| --- | --- | --- |
| 1 | الأساس: العملاء والدومينات والمصادقة والاشتراكات والصلاحيات ولوحة Super Admin | **هذا المستند** |
| 2 | الدومين المخصص: DNS وSSL | مؤجلة، والتصميم يدعمها من الآن |
| 3 | وحدات المخزون والفواتير | لم تُصمَّم بعد |
| لاحقًا | بوابة دفع تلقائية | غير محددة |

المطلوب منك الآن: **تنفيذ المرحلة الأولى فقط**، وحدودها في القسم 2.

## 1) السياق والقرارات المحسومة

الجدول التالي هو ما حسمه صاحب المشروع، وهو الأساس الذي لا يُغيَّر دون الرجوع إليه.

| القرار | القيمة |
| --- | --- |
| المنتج | SaaS لإدارة المحلات: المخزون والفواتير |
| نموذج البيع | يُباع لعدة عملاء بكود واحد ونشر واحد، وأي تعديل يصل للجميع |
| عزل البيانات | Single DB مع `tenant_id` الآن، مع إمكانية نقل عميل كبير لقاعدة مستقلة لاحقًا (Hybrid) |
| تحديد العميل | المرحلة 1: subdomain مثل `shop1.<base-domain>`. المرحلة 2: دومين مخصص لكل عميل. **النظام يدعم الاثنين من التصميم الأول** |
| المستخدمون | كل مستخدم تابع لعميل واحد فقط |
| الباقات | باقات ثابتة + تخصيص لعميل بعينه (ميزات وحدود وسعر) |
| الدفع | يدوي حاليًا: التفعيل والتجديد من لوحة Super Admin |
| انتهاء الاشتراك | يحدد صاحب المشروع لكل ميزة: تبقى مفتوحة أو للقراءة فقط أو تُقفل |
| الباكدج | `stancl/tenancy` **معتمد من صاحب المشروع**. تحقق من توافقه مع Laravel 12 وFilament v5 قبل التثبيت، وإن ظهر عدم توافق فلا تنتقل لبديل دون الرجوع إليه |
| تنظيم الكود | Laravel، والفئات مجمّعة في مجلدات فرعية حسب الدومين (`Services/Billing`، `Enums/Tenancy`...) وليس في مجلدات مسطحة **(مقترح)** |

**الإصدارات (من جهاز صاحب المشروع):** Laravel 12 (المثبّت `12.69.2`)، وFilament v5 (المثبّت `5.8.2`)، وPHP `^8.2`. اعمل على هذه الإصدارات. يجوز استخدام إصدار Laravel أحدث فقط إذا كان يعمل على نفس نسخة PHP ويتوافق مع Filament v5 و`stancl/tenancy`، ولا ترقّ أيًّا منها دون موافقة. تحقق من توافق `stancl/tenancy` مع Laravel 12 قبل التثبيت (لم يتحقق منه كاتب الخطة). وFilament v5 لا يملك كاتب الخطة معرفة موثّقة بتفاصيله، فارجع لتوثيقه الرسمي ولا تعتمد على ما هو مكتوب هنا عنه. قاعدة البيانات: \*\*MySQL\*\* (قرار صاحب المشروع)، ويُقترح MySQL 8 أو أحدث، فتحقق من نسخة جهازه قبل البدء.

**تثبيت الإصدارات (متحقَّق منها محليًا على جهاز صاحب المشروع):** PHP `8.2.12` في سطر الأوامر، وLaravel `12.69.2`، وFilament `5.8.2`. وبناءً عليها: ثبّت `stancl/tenancy` على `^3.9` (النسخة 4 تحتاج PHP 8.4 أو أعلى وMySQL 8 أو أعلى، فلا تستخدمها)، وثبّت `spatie/laravel-permission` على `^6.0` (النسخة 7 تحتاج PHP 8.3 أو أعلى). معلومات توافق الحزم من Packagist وتوثيقها وقت كتابة الخطة، فتأكد منها بـ `composer` عند التثبيت. بعد الخطوة 1 اختبر مبكرًا توافق Livewire 4 (القادم مع Filament v5) مع `stancl/tenancy` v3: لوحة Filament تعمل على الدومين المركزي وطلبات Livewire اللاحقة تنجح. وإن ظهر تعارض فلا تنتقل لإصدار آخر دون الرجوع لصاحب المشروع.

**الصلاحيات:** مخصصة لكل عميل باستخدام `spatie/laravel-permission` (قرار صاحب المشروع)، ولذلك لا يوجد عمود `role` في جدول `users`. التفاصيل في القسم 6.6.

## 2) نطاق المرحلة الأولى (معتمد)

الهدف: أساس يعمل ويُختبر قبل بناء أي وحدة تجارية. لا تبنِ شيئًا من بند «خارج النطاق».

### داخل النطاق

1. تثبيت وضبط multi-tenancy بوضع Single DB.
2. تحديد العميل من subdomain أو من دومين مخصص بنفس الكود، عبر جدول `domains`.
3. تسجيل الدخول لمستخدمي العميل (guard `web`) ولمشرفي المنصة (guard `admin`) بشكل منفصل.
4. الجداول: `tenants` و`domains` و`users` و`sequences` و`super_admins`.
5. نظام الباقات والميزات والاشتراكات، مع `FeatureGate` وmiddleware.
6. الصلاحيات المخصصة لكل عميل بـ `spatie/laravel-permission` (القسم 6.6): الإعداد، وكتالوج الصلاحيات، ودور `owner`، وفحص الصلاحيات في الـ backend. أما شاشة العميل لإدارة المستخدمين والأدوار فليست ضمن المرحلة 1 **(مقترح)**.
7. لوحة Super Admin: إنشاء عميل، وإدارة اشتراكه وميزاته ودوميناته.
8. Seeders للباقات والميزات والصلاحيات الأولية، واختبارات العزل والاشتراكات والصلاحيات.

### خارج النطاق (مؤجل)

- وحدات وجداول المخزون والفواتير. تُصمَّم لاحقًا، والمرحلة 1 تجهّز لها فقط الـ Trait و`sequences` وكتالوج الميزات.
- أتمتة SSL وإعداد DNS للدومينات المخصصة (المرحلة 2). لكن بنية `domains` والـ resolver تدعمها من الآن.
- بوابة دفع وwebhooks.
- نقل عميل فعليًا لقاعدة مستقلة. المطلوب فقط ألا يمنعه الكود: التزم بقواعد العزل في القسم 8.
- نظام الإشعارات وواجهات العميل التجارية.

## 3) المعمارية

كود واحد وقاعدة بيانات واحدة. كل جدول يخص عميلًا يحمل `tenant_id`، ويُحدَّد العميل من الـ host الذي وصل منه الطلب.

### 3.1) تحديد العميل من الـ host (subdomain ودومين مخصص معًا)

جدول `domains` يحتوي النوعين، فلا يختلف الكود بين المرحلة 1 والمرحلة 2:

| `type` | قيمة `domain` | مثال | المرحلة |
| --- | --- | --- | --- |
| `subdomain` | الـ slug فقط | `shop1` ← `shop1.app.example.com` | 1 |
| `custom` | الـ host كاملًا | `shop.client.com` | 2 |

قاعدة الحسم، بالترتيب:

1. اقرأ الـ host من الطلب.
2. إن كان ضمن `CENTRAL_DOMAINS` فالطلب مركزي (Super Admin والصفحات العامة) ولا يوجد tenant.
3. إن انتهى الـ host بـ `.{APP_BASE_DOMAIN}` فاستخرج الـ slug وابحث عنه في `domains` بنوع `subdomain`.
4. غير ذلك: ابحث عن الـ host كاملًا بنوع `custom` وبشرط `verified_at` غير فارغ.
5. لا تطابق ← 404. العميل بحالة `suspended` ← صفحة إيقاف (403).

الـ middleware الجاهز في الباكدج (v3) يطابق قيمة الدومين فقط ولا يفحص `type` ولا `verified_at` **(معلومة من مراجعة خارجية ومن معرفة كاتب الخطة، فتحقق منها في كود الإصدار المثبّت)**. لذلك اكتب resolver مخصصًا `App\Services\Tenancy\DomainTenantResolver` بقاعدة الحسم أعلاه وسجّله كـ resolver للباكدج أو في middleware خاص. هذا إلزامي وليس بديلًا احتياطيًا.

متغيرات البيئة: `APP_BASE_DOMAIN` (مثل `app.example.com`)، و`CENTRAL_DOMAINS` (قائمة تشمل الدومينات المركزية)، و`ADMIN_DOMAIN` (دومين واحد تُحصر عليه لوحة Filament، ويكون من ضمن `CENTRAL_DOMAINS`).

الـ slug: حروف إنجليزية صغيرة وأرقام وشرطة، من 3 إلى 32 حرفًا، لا يبدأ ولا ينتهي بشرطة، فريد. محظور: `www` و`admin` و`app` و`api` و`mail` و`static` و`assets` و`cdn` و`support` و`status`.

للتطوير المحلي: `shop1.localhost` يعمل غالبًا في المتصفحات الحديثة بلا إعداد إضافي (استنتاج، تحقق منه).

### 3.2) دورة الطلب

1. Middleware الـ resolver يحدد الـ tenant ويضعه في context الباكدج.
2. يُفحص `tenants.status`: الموقوف يُحجب.
3. Guard `web` يتحقق من المستخدم ويشترط أن `user.tenant_id` يساوي الـ tenant الحالي، وإلا يُرفض الدخول.
4. Middleware `feature:<key>` يسأل `FeatureGate` (القسم 6).
5. كل Model عبر `BelongsToTenant` يُفلتر تلقائيًا بالـ tenant الحالي.
6. الـ Cache والـ Storage والـ Queue كلها tenant-aware.

### 3.3) وضع Single DB في الباكدج (مقترح، تحقق من التوثيق)

- عطّل bootstrapper قاعدة البيانات المنفصلة في `config/tenancy.php`، وعطّل كذلك `CacheTenancyBootstrapper`: عزل الـ cache فيه يحتاج store يدعم الـ tags (مثل Redis) **(من توثيق الباكدج v3)**، والمرحلة 1 على MySQL وبلا Redis. استخدم مفاتيح cache تحمل `tenant_id` (القاعدة 8 في القسم 8). وأبقِ bootstrapper الـ queue.
- لا تفعّل `FilesystemTenancyBootstrapper` في المرحلة 1 (لا يوجد رفع ملفات فيها). وملفات العميل لاحقًا بمسار يدوي `tenants/{id}/...` حسب القاعدة 8 **(مقترح)**.
- أنشئ Trait مخصصًا `App\Concerns\BelongsToTenant` يغلّف Trait الباكدج، ولا تستخدم Trait الباكدج مباشرة في الـ Models. السبب: نقطة واحدة للتعديل لو انتقل عميل لقاعدة مستقلة لاحقًا.

### العزل الآمن عند غياب السياق (إلزامي)

الـ scope الافتراضي في الباكدج لا يضيف أي فلتر إذا لم يوجد tenant context **(من README باكدج طرف ثالث يبني على stancl، فتحقق من كود الإصدار المثبّت)**، فيرجع الاستعلام كل الصفوف. لذلك:

- الـ Trait `App\Concerns\BelongsToTenant` يرمي `App\Exceptions\Tenancy\TenantContextMissingException` عند أي قراءة أو كتابة على موديل بيانات عميل بلا context.
- المسار الاستثنائي الوحيد هو `TenantContext::central(fn () => ...)`، ويُستخدم فقط في Services لوحة الإدارة وFilament Resources والأوامر والـ seeders.
- الـ Jobs تُشغَّل داخل tenant context ولا تستخدم المسار المركزي.
- اختبارات: استعلام بلا context يرمي الاستثناء، ولوحة Filament تحمّل صفحاتها وطلبات Livewire اللاحقة على `ADMIN_DOMAIN` فقط، ومسار `/admin` على دومين عميل يعطي 404.

### 3.4) ما يُبنى الآن ليدعم الدومين المخصص لاحقًا

- جدول `domains` بأعمدة `type` و`verified_at` كما في القسم 5.
- لا تفترض أن `APP_URL` هو host العميل: ولّد الروابط (إيميلات، إعادة تعيين كلمة المرور، روابط الفواتير) من الدومين الأساسي للعميل عبر helper واحد `TenantUrl::for($tenant)`.
- لا تضبط `SESSION_DOMAIN` على `.{APP_BASE_DOMAIN}`: الدومين المخصص خارجه، فاترك الكوكي لكل host.
- إعداد SSL وDNS للدومين المخصص خارج المرحلة 1، ولا تكتب له كودًا الآن.

## 4) هيكل المشروع والملفات

الفئات مجمّعة في مجلدات فرعية حسب الدومين. أنشئ هذه الملفات بهذه الأسماء، ولا تضف مجلدات مسطحة جديدة.

```
app/
├── Concerns/
│   └── BelongsToTenant.php          # wraps the package trait (single place to change later)
├── Console/Commands/
│   └── CreateSuperAdmin.php         # php artisan admin:create
├── Enums/
│   ├── Tenancy/
│   │   ├── TenantStatus.php         # active, trial, suspended
│   │   └── DomainType.php           # subdomain, custom
│   ├── Billing/
│   │   ├── FeatureKey.php           # all feature keys in one place
│   │   ├── FeatureType.php          # boolean, limit
│   │   ├── ExpiryBehavior.php       # keep, read_only, lock
│   │   ├── SubscriptionStatus.php   # trial, active, canceled (past_due is out of phase 1)
│   │   └── SubscriptionEventType.php
│   └── Permissions/
│       └── PermissionKey.php             # permission names by module (guard web)
├── Filament/
│   └── Admin/                       # Super Admin panel (central domains only)
│       ├── Pages/Dashboard.php
│       ├── Resources/
│       │   ├── TenantResource.php   # relation managers: domains, subscriptions, tenant features, events (read-only)
│       │   ├── PlanResource.php     # relation manager for feature_plan
│       │   ├── FeatureResource.php
│       │   └── SuperAdminResource.php
│       └── Actions/                 # thin wrappers that call the Services (activate, renew, change plan, extend, cancel)
├── Http/
│   ├── Controllers/Tenant/          # tenant side, phase 1: login + simple dashboard
│   │   ├── Auth/LoginController.php
│   │   └── DashboardController.php
│   └── Middleware/
│       ├── EnsureTenantIsActive.php
│       ├── EnsureUserBelongsToTenant.php
│       └── EnsureFeature.php        # alias: feature:<key>
├── Models/
│   ├── Tenancy/ (Tenant.php, Domain.php)
│   ├── Billing/ (Plan.php, Feature.php, Subscription.php, TenantFeature.php, SubscriptionEvent.php)
│   ├── Admin/SuperAdmin.php
│   ├── Common/Sequence.php
│   └── User.php
├── Providers/Filament/
│   └── AdminPanelProvider.php       # panel id: admin, guard: admin, path: /admin
├── Rules/
│   └── ValidTenantSlug.php
└── Services/
    ├── Tenancy/ (TenantProvisioner.php, DomainService.php, TenantUrl.php, DomainTenantResolver.php, TenantContext.php, UserService.php, RoleService.php)
    ├── Billing/ (FeatureGate.php, SubscriptionService.php)
    └── Common/SequenceService.php

config/
├── tenancy.php                      # package config (single DB mode)
└── billing.php                      # default grace_days, trial_days, reserved slugs, default expiry behavior

database/
├── migrations/                      # order in section 5
├── factories/
└── seeders/ (DatabaseSeeder, PlanSeeder, FeatureSeeder, SuperAdminSeeder, PermissionSeeder)

routes/
├── web.php                          # public central pages
└── tenant.php                       # tenant routes, behind the tenancy middleware
                                     # (the Filament panel registers its own /admin routes)

tests/
├── Feature/Tenancy/
├── Feature/Billing/
├── Feature/Admin/
└── Unit/
```

مسؤوليات أهم الملفات:

| الملف | المسؤولية |
| --- | --- |
| `Concerns/BelongsToTenant` | يضيف الـ Global Scope ويعبّي `tenant_id` تلقائيًا عند الإنشاء |
| `Services/Tenancy/TenantProvisioner` | ينشئ عميلًا جديدًا في transaction واحدة: tenant + subdomain + مالك + اشتراك تجريبي |
| `Services/Tenancy/DomainService` | إضافة دومين وإعداده والتحقق منه، وتعيين الأساسي |
| `Services/Tenancy/TenantUrl` | المصدر الوحيد لتوليد روابط العميل من دومينه الأساسي |
| `Services/Billing/FeatureGate` | الحسم في الميزات (القسم 6) |
| `Services/Billing/SubscriptionService` | تفعيل وتجديد وتغيير باقة وتمديد وإلغاء، مع تسجيل كل حدث |
| `Services/Common/SequenceService` | حجز رقم تسلسلي داخل transaction مع `lockForUpdate` |
| `Http/Middleware/EnsureFeature` | يربط نوع الطلب بـ `canRead` أو `canWrite` |

الـ guards في `config/auth.php`: `web` لمستخدمي العميل (provider `users`) و`admin` لمشرفي المنصة (provider `super_admins`)، ولا يشتركان في session أو جدول.

## 5) قاعدة البيانات

قاعدة عامة: كل جدول يخص بيانات عميل مباشرةً يحمل `tenant_id` (FK، `cascadeOnDelete`)، وكل index مركّب في هذه الجداول يبدأ بـ `tenant_id`. ملكية `subscription_events` للمحل تُحدَّد عبر الاشتراك المرتبط به. القيم النصية للحالات تقابل Enums في `app/Enums`، ولا تُكتب قيم نصية حرة في الكود.

**استثناءات `BelongsToTenant`:** `Domain` و`Subscription` و`TenantFeature` تحمل `tenant_id`، وتُستعلم عبر علاقة من `Tenant` أو بشرط `tenant_id` صريح. الـ resolver يبحث عن `Domain` بالـ host لتحديد المحل أولًا. `SubscriptionEvent` يتبع المحل عبر `Subscription`، ويُستعلم عنه عبر اشتراك مقيّد بالمحل. جداول بيانات العميل (`users` و`sequences` ولاحقًا المخزون والفواتير) تستخدم الـ Trait.

**ملاحظات MySQL (مقترح):** استخدم جداول InnoDB (مطلوبة لـ `lockForUpdate` في `SequenceService`)، وترميز `utf8mb4` مع collation `utf8mb4_unicode_ci` للنص العربي. هذا الـ collation غير حساس لحالة الحروف، فالـ unique على `domain` و`email` لا يميّز بينها، وهو المطلوب. أعمدة JSON (`settings`، `meta`) تعمل على MySQL 5.7 فما فوق.

### `tenants`

| العمود | النوع | ملاحظات |
| --- | --- | --- |
| `id` | bigIncrements |  |
| `name` | string(150) | اسم المحل |
| `status` | string(20) | `TenantStatus`، الافتراضي `trial` |
| `trial_ends_at` | timestamp، nullable |  |
| `settings` | json، nullable | عملة، ضريبة، شعار... |
| `timestamps` + `softDeletes` |  |  |

Index على `status`. الباكدج يجهّز افتراضيًا عمود `data` JSON، فعرّف الأعمدة أعلاه كأعمدة مخصصة في موديل `Tenant` **(مقترح، راجع توثيق الإصدار)**.

### `domains`

| العمود | النوع | ملاحظات |
| --- | --- | --- |
| `id` | bigIncrements |  |
| `tenant_id` | FK |  |
| `domain` | string(255) | **unique**. للـ subdomain الـ slug فقط، وللمخصص الـ host كاملًا بحروف صغيرة ودون scheme أو port |
| `type` | string(20) | `DomainType`: `subdomain` أو `custom` |
| `is_primary` | boolean | افتراضي false |
| `verified_at` | timestamp، nullable | يُعبَّأ فورًا لصفوف `subdomain`، وبعد التحقق من الـ DNS لصفوف `custom` |
| `timestamps` |  |  |

Index على `tenant_id`. لكل عميل دومين أساسي واحد بالضبط، ويُفرض ذلك في `DomainService` (لا يكفي الـ DB).

### `super_admins`

`id`، `name`، `email` (unique)، `password`، `is_active` (افتراضي true)، `remember_token`، `timestamps`.

### `users`

| العمود | النوع | ملاحظات |
| --- | --- | --- |
| `id` | bigIncrements |  |
| `tenant_id` | FK |  |
| `name`، `email`، `password` |  |  |
| `type` | string(50)، nullable | **احتياطي ومفتوح**: نص حر بلا Enum وبلا قائمة قيم في الكود، لتصنيف المستخدمين وفلترتهم. لا يدخل في أي فحص صلاحية |
| `meta` | json، nullable | **احتياطي ومفتوح** لأي بيانات إضافية للمستخدم بلا migration جديدة. لا يدخل في أي فحص صلاحية، ولا تُخزَّن فيه كلمات مرور ولا أرقام هوية أو بطاقات. ما يحتاج بحثًا أو فلترة متكررة يُنقل لعمود مستقل |
| `is_active` | boolean | افتراضي true |
| `must_change_password` | boolean | افتراضي false. يُضبط true عند إنشاء المالك أو إعادة تعيين كلمة مروره، ويُجبره على التغيير قبل أي صفحة أخرى |
| `email_verified_at`، `remember_token`، `timestamps` |  |  |

**Unique مركّب** `(tenant_id, email)`، وليس `email` وحده. جدول `password_reset_tokens` يحمل `tenant_id` ومفتاحه الأساسي `(tenant_id, email)`.

لا يوجد عمود `role`: الأدوار والصلاحيات عبر `spatie/laravel-permission` (القسم 6.6). أما `type` فعمود احتياطي مقصود: نص حر (`string`) بلا Enum ولا قائمة قيم ثابتة في الكود، لأن أنواع المستخدمين لم تُحدَّد بعد، ويُترك مفتوحًا للإضافة. يُستخدم للتصنيف والفلترة والعرض فقط، ولا يُبنى عليه أي فحص صلاحية أو منطق وصول. أضف Index على `(tenant_id, type)`.

### `sequences`

| العمود | النوع | ملاحظات |
| --- | --- | --- |
| `id` | bigIncrements |  |
| `tenant_id` | FK |  |
| `type` | string(30) | مثل `invoice` |
| `year` | unsignedSmallInteger | **NOT NULL، والقيمة 0 تعني «بلا إعادة سنوية»**. لا تستخدم NULL لأن الـ unique يسمح بتكرار NULL |
| `last_number` | unsignedBigInteger | افتراضي 0 |
| `timestamps` |  |  |

**Unique مركّب** `(tenant_id, type, year)`.

### `plans` و`features` و`feature_plan`

| الجدول | الأعمدة |
| --- | --- |
| `plans` | `id`، `slug` (unique)، `name`، `price` decimal(10,2)، `billing_period` (`monthly` أو `yearly`)، `is_active`، `sort_order`، `timestamps` |
| `features` | `id`، `key` string(64) unique، `name`، `type` (`FeatureType`)، `expiry_behavior` (`ExpiryBehavior`، الافتراضي `lock`)، `timestamps` |
| `feature_plan` | `plan_id`، `feature_id`، `value` unsignedInteger nullable (للحدود الرقمية، وNULL تعني بلا حد)، `expiry_behavior` nullable (يتقدّم على قيمة الميزة لهذه الباقة). Unique على `(plan_id, feature_id)` |

هذه الجداول مشتركة بين العملاء: **بلا `tenant_id`**، ولا تستخدم `BelongsToTenant` في موديلاتها.

### `subscriptions`

| العمود | النوع | ملاحظات |
| --- | --- | --- |
| `id` | bigIncrements |  |
| `tenant_id`، `plan_id` | FK |  |
| `status` | string(20) | `SubscriptionStatus` |
| `starts_at` | timestamp |  |
| `ends_at` | timestamp، nullable |  |
| `grace_days` | unsignedSmallInteger، nullable | override لكل عميل، وNULL تعني القيمة الافتراضية من `config/billing.php` |
| `custom_price` | decimal(10,2)، nullable | يتقدّم على سعر الباقة |
| `payment_note` | text، nullable | مرجع التحويل أو طريقة الدفع |
| `timestamps` |  |  |

Index على `(tenant_id, status)`. **الاشتراك الحالي** للعميل هو أحدث اشتراك (بـ `starts_at` ثم `id`) **بغض النظر عن حالته**. إلغاؤه لا يُرجع اشتراكًا أقدم، بل تُطبَّق عليه سياسة انتهاء الميزات (القسم 6).

**قواعد المرحلة 1:** لا `past_due`، ولا `starts_at` في المستقبل (الدفع يدوي ولا توجد جدولة)، و`starts_at` لا يتجاوز الوقت الحالي عند الإنشاء. `ends_at` إلزامي للاشتراك `trial` ويجوز أن يكون فارغًا للاشتراك `active` (بلا تاريخ انتهاء). كل العمليات التالية تمر عبر `SubscriptionService`، ويُسجَّل حدث العملية في نفس الـ transaction.

- **التفعيل:** يحوّل الاشتراك التجريبي (`trial`) إلى `active` في نفس الصف عند تسجيل الدفع. للفترة المحددة، يُحسب `ends_at` من وقت التفعيل مضافًا إليه شهر أو سنة حسب `billing_period`. عند التفعيل بعد الإلغاء، يُنشأ صف `active` جديد.
- **التجديد:** للاشتراك `active` ذي تاريخ انتهاء، يضيف شهرًا أو سنة حسب `billing_period` إلى الأبعد بين وقت التجديد و`ends_at` الحالي. يُعدَّل نفس الصف سواء كان الاشتراك داخل مدته أو في فترة السماح أو بعد انتهائها.
- **التمديد:** يضيف عددًا موجبًا من الأيام يحدده المشرف إلى `ends_at` في نفس الصف لاشتراك `trial` أو `active` له تاريخ انتهاء، مع إبقاء حالته المخزّنة. الاشتراك `active` بلا تاريخ انتهاء لا يحتاج تجديدًا أو تمديدًا.
- إنشاء الاشتراك الأول يتم وقت تجهيز المحل. بعد ذلك يُنشأ صف جديد عند تغيير الباقة أو التفعيل بعد الإلغاء.

### `tenant_features` و`subscription_events`

| الجدول | الأعمدة |
| --- | --- |
| `tenant_features` | `id`، `tenant_id`، `feature_id`، `enabled` boolean، `value` unsignedInteger nullable، `expires_at` timestamp nullable، `timestamps`. Unique على `(tenant_id, feature_id)` |
| `subscription_events` | `id`، `subscription_id` (FK إلى `subscriptions`)، `type` (`SubscriptionEventType`: activated / renewed / plan\_changed / extended / canceled)، `performed_by` (FK إلى `super_admins`، nullable)، `note` text nullable، `meta` json nullable، `created_at` |

### ترتيب الـ migrations

1. `create_tenants_table`
2. `create_domains_table`
3. `create_super_admins_table`
4. `create_users_table` (عدّل migration لارافل الافتراضية بإضافة `tenant_id`، وأنشئ `password_reset_tokens` بالمفتاح المركّب)
5. `create_sequences_table`
6. `create_plans_table`
7. `create_features_table`
8. `create_feature_plan_table`
9. `create_subscriptions_table`
10. `create_tenant_features_table`
11. `create_subscription_events_table`

## 6) نظام الاشتراكات والميزات

**المبدأ:** الكود يسأل عن «ميزة» ولا يسأل أبدًا عن «باقة». ممنوع `if ($plan === 'pro')` في أي مكان، والمسموح فقط `FeatureGate`.

### 6.1) حالة الاشتراك (مقترح)

| الحالة المحسوبة | الشرط |
| --- | --- |
| `active` | الاشتراك الحالي بحالة `trial` أو `active` وتاريخ `ends_at` لم يمر (أو فارغ) |
| `grace` | مرّ `ends_at` وما زلنا داخل `grace_days` (من الاشتراك، وإلا الافتراضي من `config/billing.php`) |
| `expired` | انتهت فترة السماح، أو الاشتراك `canceled` |
| `none` | لا يوجد اشتراك |

في حالتي `active` و`grace` يُعامَل العميل كأنه مشترك بالكامل.

### 6.2) ترتيب الحسم لميزة معيّنة

1. يوجد صف في `tenant_features` ولم ينتهِ `expires_at` **والحالة ليست `none`**: القيمة `enabled` هي الحاسمة (true تفتح القراءة والكتابة، وfalse تقفل الاثنتين)، ويُستخدم `value` منه للحدود إن وُجد. **ينطبق ذلك حتى بعد انتهاء الاشتراك.**
2. غير ذلك والحالة `active` أو `grace`: إن وُجد للميزة صف في `feature_plan` لباقة العميل فهي مفتوحة (قراءة وكتابة) وحدّها من `value`. وإن لم يوجد فهي مقفولة.
3. غير ذلك والحالة `expired`: إن لم تكن الميزة ضمن باقته فهي مقفولة. وإن كانت فيطبَّق `expiry_behavior` (قيمة `feature_plan` إن وُجدت وإلا قيمة `features`).
4. الحالة `none` (لا يوجد أي اشتراك أصلًا): كل شيء مقفول حتى لو وُجد override **(مقترح، بانتظار تأكيد صاحب المشروع)**.

| `expiry_behavior` | `canRead` | `canWrite` |
| --- | --- | --- |
| `keep` | نعم | نعم |
| `read_only` | نعم | لا |
| `lock` | لا | لا |

### 6.3) واجهة `FeatureGate`

```php
// in app/Services/Billing/FeatureGate.php
$gate = app(FeatureGate::class);           // uses the current tenant
$gate = app(FeatureGate::class)->for($tenant); // admin / jobs context

$gate->canRead(FeatureKey $key): bool;
$gate->canWrite(FeatureKey $key): bool;
$gate->limit(FeatureKey $key): ?int;       // null = unlimited, 0 = not allowed
$gate->state(): SubscriptionState;         // active | grace | expired | none
```

الـ Cache: **لا تبدأ به.** ابدأ `FeatureGate` بلا cache حتى تظهر تعديلات الباقات فورًا، وأضفه لاحقًا فقط إذا أظهر القياس حاجة. وعند إضافته: مفتاح يحمل `tenant_id`، وتعديل `feature_plan` يمسح كل عملاء الباقة (أو استخدم مفتاح إصدار عام)، وmodel observers على `Subscription` و`TenantFeature` و`FeaturePlan`، ولا تعتمد على `Cache::tags` مع driver ملفات أو قاعدة بيانات (غير مدعومة).

### 6.4) Middleware `feature:<key>`

- طلبات `GET` و`HEAD` تمر على `canRead`، وطلبات `POST` و`PUT` و`PATCH` و`DELETE` على `canWrite`.
- المرفوض: في الويب صفحة 403 برسالة ترقية الباقة، وفي JSON استجابة 403 بكود `feature_locked` أو `feature_read_only`.
- الحدود الرقمية (مثل `max_users`) لا تمر على الـ middleware، بل يفحصها الـ Service المسؤول عن الإنشاء قبل التنفيذ.

### 6.5) بيانات البذور الأولية (مقترح، أنت تعدّلها)

| `key` | النوع | `expiry_behavior` | `basic` | `pro` |
| --- | --- | --- | --- | --- |
| `invoicing` | boolean | `read_only` | نعم | نعم |
| `inventory` | boolean | `read_only` | نعم | نعم |
| `data_export` | boolean | `keep` | نعم | نعم |
| `multi_warehouse` | boolean | `lock` | لا | نعم |
| `advanced_reports` | boolean | `lock` | لا | نعم |
| `barcode_printing` | boolean | `lock` | لا | نعم |
| `max_users` | limit | `keep` | 3 | 15 |
| `max_products` | limit | `keep` | 500 | بلا حد |

أسعار الباقات في الـ seeder قيم مؤقتة (`0.00`) وليست قرارًا. قيم `config/billing.php` الافتراضية **(مقترح)**: `grace_days = 7`، `trial_days = 14`.

### 6.6) الصلاحيات المخصصة (`spatie/laravel-permission`)

السماح بعملية ما يمر على ثلاث طبقات، ولا تنجح إلا إذا وافقت الثلاث:

| الطبقة | السؤال | يجيب عنها |
| --- | --- | --- |
| الاشتراك | هل يملك العميل هذه الميزة، وبأي سلوك؟ | `FeatureGate` وmiddleware `feature:<key>` (6.2) |
| الصلاحية | هل هذا المستخدم مسموح له بالعملية؟ | spatie عبر `can()` أو Policies |
| الحد الرقمي | هل تجاوز العميل الحد؟ | `FeatureGate::limit()` |

ميزة مقفولة أو للقراءة فقط بسبب الاشتراك تمنع الكتابة حتى لو كان الدور يسمح بها.

### الإعداد (مقترح: من معرفتي بالباكدج، تحقق من توثيق الإصدار المثبّت)

1. ثبّت `spatie/laravel-permission` بأحدث إصدار متوافق مع Laravel 12.
2. في `config/permission.php` فعّل `teams` وعيّن `column_names.team_foreign_key` إلى `tenant_id`، **قبل** تشغيل migration الباكدج.
3. الصلاحيات على الـ guard `web` فقط. لا صلاحيات لـ guard `admin` في المرحلة 1: كل مشرف له كل الصلاحيات.
4. أضف `HasRoles` إلى `User`.
5. بعد تحديد الـ tenant وقبل أي فحص صلاحية نادِ `setPermissionsTeamId($tenant->id)` في الـ middleware، وكذلك داخل الـ Jobs tenant-aware.
6. ضع migration الباكدج بعد migration `users` في الترتيب.

### الهيكل

- **الصلاحيات** عامة (مشتركة بين العملاء) ومعرّفة في الكود بـ Enum `App\Enums\Permissions\PermissionKey` بصيغة `<module>.<action>` مثل `users.view`. يزامنها `PermissionSeeder` (آمن للتكرار، ويُشغَّل مع كل نشر). المستخدم لا ينشئ صلاحيات.
- **الأدوار** لكل عميل (team = tenant)، وصاحب العميل يخصصها: ينشئ أدوارًا ويختار لها صلاحيات من الكتالوج. ممنوع إنشاء دور بـ `tenant_id` فارغ.
- كل صلاحية مرتبطة بميزة عبر `PermissionKey::feature()` (تعيد `FeatureKey` أو `null`)، ولا تُعرض للعميل صلاحيات ميزات لا يملكها.
- الصلاحيات تُمنح للأدوار فقط ولا تُمنح مباشرة للمستخدم (لا `model_has_permissions`) **(مقترح لتبسيط الإدارة)**.

**المستخدم يمكن أن يحمل أكثر من دور** (قرار صاحب المشروع). صلاحياته الفعلية هي مجموع صلاحيات كل أدواره، والأدوار تمنح صلاحيات فقط ولا يوجد «منع» يلغي صلاحية دور آخر. يُضاف اختبار: مستخدم بدورين يحصل على اتحاد صلاحياتهما، وسحب أحد الدورين يُسقط صلاحياته هو فقط.

### دور `owner`

- ينشئه `TenantProvisioner` لكل عميل ويُسنده لمالكه، واسمه محجوز.
- لا يُعدَّل ولا يُحذف، ولا يُسحب من آخر مالك: يجب أن يبقى مالك واحد على الأقل، ويفرض ذلك `UserService`.
- يمرّ على كل فحوص الصلاحيات عبر `Gate::before` **(مقترح)** بدل تحديث الدور مع كل صلاحية جديدة. لكنه لا يتجاوز طبقة الاشتراك.

### كتالوج المرحلة 1 **(مقترح)**

| الصلاحية | الميزة المرتبطة |
| --- | --- |
| `users.view` و`users.create` و`users.update` و`users.delete` | بلا ميزة (أساسية) |
| `roles.view` و`roles.manage` | بلا ميزة (أساسية) |

صلاحيات المخزون والفواتير (`inventory.*` و`invoices.*`) تُضاف مع وحداتها لاحقًا.

### الخدمات

- `UserService`: إنشاء وتعديل وتعطيل المستخدم، ويفحص `FeatureGate::limit()` لـ `max_users`، ويضمن بقاء مالك واحد على الأقل.
- `RoleService`: إنشاء وتعديل وحذف دور العميل ومزامنة صلاحياته. يرفض صلاحية ميزة لا يملكها العميل، ويمنع تعديل `owner`.
- كلاهما يمسح كاش الصلاحيات (`forgetCachedPermissions`) بعد أي تغيير.

### قواعد

- كل فحص يستخدم أسماء من `PermissionKey` عبر `can()` أو Policies، ولا تُكتب أسماء الصلاحيات نصًا حرًا.
- الـ Controllers وموارد Filament تفحص الصلاحية عبر Policy.

### اختبارات مطلوبة

- دور بنفس الاسم في عميلين لا يتصادم، وتعديل دور عميل A لا يغيّر عميل B.
- مستخدم عميل A لا يحمل صلاحيات دور عميل B ولو بنفس الاسم، والـ team id يُضبط لكل طلب ولكل Job بلا تسريب بين طلبين متتاليين.
- `owner` يمر على كل الصلاحيات لكنه يُرفض لو الميزة مقفولة أو للقراءة فقط بسبب الاشتراك.
- `UserService` يرفض الإنشاء عند بلوغ `max_users`، ويرفض حذف أو إزاحة آخر مالك.
- `RoleService` يرفض منح صلاحية ميزة لا يملكها العميل، ويمنع تعديل `owner`.

### نقاط مفتوحة للصلاحيات

- كتالوج الصلاحيات النهائي.
- شاشة العميل لإدارة المستخدمين والأدوار تتبع قرار واجهة العميل (Filament أم Blade) في القسم 11.

## 7) لوحة Super Admin

تعمل على دومين واحد `ADMIN_DOMAIN` (من ضمن `CENTRAL_DOMAINS`) تحت `/admin` بـ guard `admin` فقط، وتُبنى بـ **Filament v5** كـ panel مستقل معرّفه `admin` (`app/Providers/Filament/AdminPanelProvider.php`) بالمسار `/admin` و`->authGuard('admin')` و`->domain(config('app.admin_domain'))`. استخدم دومينًا واحدًا بـ `domain()` ولا تستخدم `->domains([...])` بأكثر من دومين: ظهرت تقارير عن تحويل تسجيل الدخول للدومين الآخر وحاجة الـ console لضبط `Filament::currentDomain` **(من نقاشات مجتمع Filament، فتحقق من توثيق v5)**. لا تُفعَّل اللوحة على دومينات العملاء. لا يوجد تسجيل حساب جديد: يُنشأ المشرف بـ seeder أو بأمر `php artisan admin:create`. أضف throttle على تسجيل الدخول. كل شاشة أدناه تُنفَّذ كـ Resource أو Relation Manager أو Action وتستدعي الـ Services نفسها، ولا يوضع منطق أعمال داخل الـ Resource.

| الشاشة | العمليات | يمر عبر |
| --- | --- | --- |
| العملاء | قائمة وبحث وفلتر بالحالة، وإنشاء عميل | `TenantProvisioner` |
| صفحة العميل | تعديل البيانات، وتفعيل أو إيقاف (`TenantStatus`) | `Tenant` model |
| الدومينات | إضافة subdomain أو دومين مخصص، وتعيين الأساسي، وحذف غير الأساسي، وزر «تعليم كمُتحقَّق منه» يدويًا للمخصص (أتمتة التحقق مؤجلة) **(مقترح)** | `DomainService` |
| الاشتراك | تفعيل وتجديد وتغيير باقة وتمديد وإلغاء، مع `grace_days` و`custom_price` و`payment_note` | `SubscriptionService` |
| الميزات المخصصة | عرض الحالة الفعلية لكل ميزة (من الباقة أو override)، وإضافة override (`enabled` و`value` و`expires_at`) أو حذفه | `FeatureGate` + `tenant_features` |
| الباقات | إنشاء وتعديل الباقة وربط الميزات بها (`value` و`expiry_behavior`) | `plans` و`feature_plan` |
| كتالوج الميزات | تعديل `name` و`expiry_behavior` و`type`. الـ `key` لا يُعدَّل بعد الإنشاء، ولا تُحذف ميزة مستخدمة | `features` |
| سجل الأحداث | عرض `subscription_events` لكل عميل | قراءة فقط |

### إنشاء عميل (`TenantProvisioner`)

عملية واحدة داخل transaction:

1. إنشاء `tenants` بحالة `trial`.
2. إنشاء صف `domains` من نوع `subdomain` بالـ slug، `is_primary = true` و`verified_at = now()`.
3. إنشاء مستخدم بدور `owner` وكلمة مرور مؤقتة تُعرض مرة واحدة للمشرف، وإنشاء دور owner للعميل وإسناده له (القسم 6.6).
4. إنشاء اشتراك بحالة `trial` على الباقة المختارة (الافتراضية `basic`)، و`ends_at = now() + trial_days`.
5. تسجيل حدث `activated` في `subscription_events`.

أي فشل في خطوة يلغي كل الخطوات.

### قواعد اللوحة

- كل عملية كتابة تمر عبر Service ولا تُكتب داخل Resource أو Action.
- كل عملية على الاشتراك تُسجَّل في `subscription_events` مع `performed_by`.
- إيقاف عميل لا يحذف بياناته، ولا يوجد حذف نهائي لعميل في المرحلة 1 (soft delete فقط، بعد تأكيد صريح).
- لوحة Super Admin هي المكان الوحيد المسموح فيه بتجاوز الـ Global Scope (`withoutGlobalScopes`)، وبشكل صريح ومحصور داخل Services الإدارة وFilament Resources الخاصة بها (دالة getEloquentQuery).

### كلمات المرور وتسليم حساب المالك (مقترح)

- عند إنشاء المالك: كلمة مرور عشوائية قوية تُعرض للمشرف مرة واحدة، و`must_change_password = true`. لا تُرسل بالبريد في المرحلة 1.
- Middleware `EnsurePasswordChanged` يحوّل المستخدم لصفحة تغيير كلمة المرور حتى يغيّرها.
- إجراء «إعادة تعيين كلمة مرور المالك» في صفحة العميل بلوحة Super Admin: ينتج كلمة مؤقتة جديدة ويضبط العلم.
- الاستعادة الذاتية بالبريد **خارج المرحلة 1**. وعند بنائها لاحقًا لا تعتمد على الـ broker الافتراضي، لأنه يبحث عن التوكن بالبريد فقط **(من معرفة كاتب الخطة بـ Laravel، فتحقق منه)**، فيتصادم نفس البريد بين محلين. المطلوب broker واعٍ بالـ tenant مع اختبار نفس البريد في محلين. وجدول `password_reset_tokens` يُنشأ كما في القسم 5 لكنه غير مستخدم في المرحلة 1.
- أي بريد أو إشعار يخرج أثناء إنشاء العميل يُرسَل بعد نجاح الـ transaction عبر `DB::afterCommit`، حتى لا يخرج لعميل فشل إنشاؤه.

## 8) قواعد التنفيذ الإلزامية

### عزل البيانات

1. `tenant_id` في كل جدول بيانات عميل مباشرةً. `subscription_events` يتبع المحل عبر المفتاح `subscription_id`، ويُقيَّد الوصول لأحداثه بـ `subscriptions.tenant_id`.
2. كل Model بيانات عميل يستخدم `App\Concerns\BelongsToTenant` مع استثناءات القسم 5. موديلات الباقات والميزات المشتركة لا تستخدمه.
3. لا تقبل `tenant_id` من الـ request أبدًا (لا input ولا حقل مخفي ولا route parameter). يُؤخذ من الـ tenant الحالي فقط، ولا يدخل في `$fillable`.
4. كل قاعدة `unique` أو `exists` في التحقق مقيّدة بالـ tenant (Rule مخصصة `UniqueInTenant` أو `->where('tenant_id', ...)`).
5. ممنوع الـ joins أو العلاقات بين بيانات عملاء مختلفين.
6. ممنوع `DB::table` والـ raw queries على جداول العميل دون شرط `tenant_id` صريح. في `subscription_events` يُطبّق الشرط على الاشتراك المرتبط. الأصل استخدام Eloquent.
7. `withoutGlobalScopes` محظور خارج Services لوحة الإدارة وFilament Resources الخاصة بها.
8. مفاتيح الـ Cache تحمل `tenant_id`، وملفات العميل في `tenants/{id}/...`، وكل Job يحمل الـ tenant ويفعّله قبل التنفيذ.
9. أي رابط يُرسل للعميل (إيميل أو غيره) يُولَّد من `TenantUrl::for($tenant)`، ولا يُبنى من `APP_URL`.
10. ترقيم الفواتير وما شابهها عبر `SequenceService` فقط.

### الكود

- القيم الثابتة (حالات، أدوار، أنواع) في Enums داخل `app/Enums/<Domain>/`، ولا magic strings.
- Controllers رفيعة: التحقق في FormRequest، والمنطق في Service. لا منطق أعمال في الـ Models غير العلاقات والـ scopes.
- `declare(strict_types=1)` وأنواع صريحة للمعاملات والقيم المُرجَعة **(مقترح)**.
- المبالغ `decimal` ولا `float`، والتواريخ تُخزَّن UTC.
- لا تعدّل migration بعد دمجها، وكل migration لها `down()`.
- الكود والـ commits بالإنجليزية. النصوص المعروضة للمستخدم في ملفات `lang` وليس داخل الـ views (لغة الواجهة غير محسومة، انظر القسم 11).

### العمل

- لا تخرج عن نطاق القسم 2، ولا تضف باكدجات غير المذكورة دون موافقة.
- لا تحسم أي بند من «نقاط مفتوحة».
- ما اعتمدت فيه على معلومة ليست في هذا المستند (مثل سلوك باكدج في إصدار معيّن) اذكره صراحة في ردك على أنه استنتاج يحتاج تحققًا، ولا تقدّمه كأنه مؤكد.
- في نهاية كل خطوة من القسم 9 اذكر: الملفات المنشأة أو المعدّلة، وما اختُبر، وما لم يُنفَّذ ولماذا.

### قواعد أضيفت بعد المراجعة

- لا cache في `FeatureGate` في البداية (6.3).
- اختبار صريح: مالك المحل A على دومين المحل B يُرفض. ولا يعتمد `Gate::before` على وجود دور `owner` فقط، بل على ضبط team id للـ tenant الحالي.
- أي إرسال بريد أو إشعار داخل عملية فيها transaction يمر عبر `DB::afterCommit`.
- لا تستخدم `->domains()` بأكثر من دومين في Filament (القسم 7).
- لا تثبّت `stancl/tenancy` v4 ولا `spatie/laravel-permission` v7 (الإصدارات في القسم 1).
- أي عملية إدارية على بيانات عميل (مثل إعادة تعيين كلمة مرور المالك) تبدأ من علاقة المحل المحدد (مثل `$tenant->users()`) أو بشرط `tenant_id` صريح، ولا تبحث بالبريد وحده داخل `TenantContext::central()`.

## 9) ترتيب التنفيذ (مقترح)

نفّذ بهذا الترتيب. لا تبدأ خطوة قبل أن يتحقق معيار قبول السابقة.

| # | الخطوة | معيار القبول |
| --- | --- | --- |
| 1 | **تهيئة المشروع:** Laravel `12.69.2`، تثبيت Filament v5 و`stancl/tenancy` على `^3.9` و`spatie/laravel-permission` على `^6.0`، متغيرات `APP_BASE_DOMAIN` و`CENTRAL_DOMAINS` و`ADMIN_DOMAIN`، تعطيل bootstrapper قاعدة البيانات في `config/tenancy.php`، وإنشاء `config/billing.php` | يعمل على PHP 8.2.12، و`composer install` من الصفر بلا تعارض، ولوحة Filament الفارغة تفتح على `ADMIN_DOMAIN` وطلبات Livewire اللاحقة تنجح (اختبار التوافق في القسم 1) |
| 2 | **الأساس:** Enums، migrations الجداول 1 إلى 5 (مع `type` و`meta` و`must_change_password`)، migration spatie بعد `users` (مع ضبط `teams` قبلها)، الموديلات، `PermissionSeeder`، والـ Trait `BelongsToTenant` بسلوك الفشل الآمن (3.3) | `migrate:fresh` ينجح، والـ unique المركّب `(tenant_id, email)` يعمل، واستعلام بلا context يرمي الاستثناء |
| 3 | **تحديد العميل:** `DomainTenantResolver` (3.1)، وmiddleware `EnsureTenantIsActive` و`EnsureUserBelongsToTenant`، و`routes/tenant.php`، و`TenantUrl` | تنجح كل حالات القسم 10 (أ) |
| 4 | **المصادقة:** guards `web` و`admin`، دخول المستخدم (بشرط تطابق الـ tenant)، دخول المشرف، `EnsurePasswordChanged`، وأمر `admin:create` | مستخدم عميل A لا يدخل من دومين عميل B، وsession المشرف منفصلة عن session العميل |
| 5 | **أول تسليم:** `TenantProvisioner` مبسّط (tenant + subdomain + مالك بدور `owner`) ولوحة Filament بسيطة لإنشاء العملاء وإعادة تعيين كلمة مرور المالك | إنشاء محلين تجريبيين بإرسال نموذج الإنشاء في لوحة Filament (بيانات اختبار فقط)، ودخول مالك كل محل على دومينه، واختبارات العزل (10-ب) تمر، ومالك A مرفوض على دومين B |
| 6 | **الترقيم:** `SequenceService` | اختبار تزامن: 50 طلبًا متوازيًا لا يكرّر رقمًا |
| 7 | **الاشتراكات (بيانات):** migrations الجداول 6 إلى 11، الموديلات والـ Enums، والـ Seeders (6.5) | `migrate:fresh --seed` ينجح وتظهر الباقات والميزات |
| 8 | **`FeatureGate` بلا cache** و`EnsureFeature` و`UserService` و`RoleService` | تنجح مصفوفة 10 (ج) واختبارات 6.6 |
| 9 | **Services الإدارة واللوحة كاملة:** `SubscriptionService` و`DomainService`، وتوسعة `TenantProvisioner` بالاشتراك التجريبي، وباقي شاشات Super Admin (الدومينات، الاشتراك، الميزات المخصصة، الباقات، الكتالوج، السجل) | إنشاء عميل كامل داخل transaction وفشل أي خطوة يلغي الكل، وكل عملية اشتراك تُسجَّل في `subscription_events`، ولكل شاشة Feature tests |
| 10 | **مراجعة نهائية** لقواعد القسم 8: ابحث عن `withoutGlobalScopes` و`DB::table` و`tenant_id` داخل `$fillable` | لا مخالفات خارج المسموح، وكل الاختبارات تمر |

**ملاحظات على الترتيب:** الخطوة 5 هي «أول تسليم»: محلان تجريبيان ودخول مالك كل محل واختبارات العزل ولوحة بسيطة، قبل بناء الاشتراكات. **المحلان بيانات اختبار فقط:** يُنشآن من لوحة Filament ضمن قبول الخطوة 5. يمكن استخدام seeder أو factory لتجهيز بيانات اختبارات أخرى. `TenantProvisioner` الكامل (مع الاشتراك التجريبي) في الخطوة 9 هو الذي ينشئ العملاء الحقيقيين. لا تُبقِ أي محل بلا اشتراك بعد تفعيل `FeatureGate`، لأنه يصير في حالة `none` وتُقفل ميزاته. ولوحة Super Admin تُنفَّذ كـ Filament Resources وRelation Managers وActions بدل Controllers وBlade، وتستدعي الـ Services نفسها. وواجهة العميل في المرحلة 1 دخول ولوحة بسيطة بـ Blade ما لم يُقرَّر غير ذلك (القسم 11).

**الصلاحيات (القسم 6.6):** إعداد الباكدج (مع `teams`) و`PermissionSeeder` في الخطوة 2، وإسناد دور `owner` داخل `TenantProvisioner` في الخطوة 5، و`UserService` و`RoleService` في الخطوة 8. اختبارات القسم 6.6 جزء من معايير القبول.

## 10) الاختبارات المطلوبة ومعايير القبول

كل ما يلي يُكتب كاختبارات آلية (Pest أو PHPUnit حسب ما يستخدمه المشروع) ويجب أن يمر.

### أ) تحديد العميل

- subdomain معروف يحل للعميل الصحيح، وslug غير موجود يعطي 404.
- دومين مخصص بـ `verified_at` معبّأ يحل للعميل الصحيح، وغير المتحقَّق منه يعطي 404.
- host مجهول يعطي 404.
- عميل `suspended` يعطي 403 على الـ subdomain وعلى الدومين المخصص.
- الدومين المركزي لا يحدد tenant، و`/admin` يعمل عليه.
- الـ host بحروف كبيرة أو مع port يُطبَّع قبل المطابقة.

### ب) العزل

- بيانات عميل A لا تظهر لعميل B: لا في الاستعلام ولا في `find` بالمعرّف ولا في route model binding (النتيجة 404).
- الإنشاء يعبّي `tenant_id` تلقائيًا، وإرسال `tenant_id` في الـ request يُتجاهل.
- نفس الإيميل ينجح في عميلين مختلفين ويفشل داخل العميل نفسه.
- مستخدم عميل A لا يدخل من دومين عميل B.
- الـ Job يعمل داخل الـ tenant الذي أُرسل منه، ومفاتيح الـ Cache لا تتصادم بين عميلين.

### ج) مصفوفة الميزات (`FeatureGate`)

| # | الحالة | الميزة وoverride | `canRead` | `canWrite` |
| --- | --- | --- | --- | --- |
| 1 | `active` | ضمن الباقة، بلا override | نعم | نعم |
| 2 | `active` | خارج الباقة، بلا override | لا | لا |
| 3 | `active` | خارج الباقة، override مفعّل | نعم | نعم |
| 4 | `active` | ضمن الباقة، override مُعطَّل | لا | لا |
| 5 | `grace` | كما في 1 | نعم | نعم |
| 6 | `expired` | ضمن الباقة، `keep` | نعم | نعم |
| 7 | `expired` | ضمن الباقة، `read_only` | نعم | لا |
| 8 | `expired` | ضمن الباقة، `lock` | لا | لا |
| 9 | `expired` | خارج الباقة | لا | لا |
| 10 | `expired` | `lock` مع override مفعّل لم ينتهِ | نعم | نعم |
| 11 | `expired` | `lock` مع override انتهى `expires_at` | لا | لا |
| 12 | `none` | أي ميزة | لا | لا |

واختبارات إضافية:

- `expiry_behavior` في `feature_plan` يتقدّم على قيمة `features`.
- `limit()`: القيمة من `feature_plan`، وقيمة override تتقدّم عليها، وNULL تعني بلا حد (`null`)، والميزة غير المتاحة تعطي 0.
- Middleware: طلب `GET` على ميزة `read_only` منتهية يمر، وطلب `POST` عليها يُرفض بـ 403 وكود `feature_read_only` في JSON.
- الـ Cache (يُنفَّذ هذا الاختبار فقط عند إضافة الـ cache لاحقًا): تغيير الاشتراك أو الـ override يمسحه فورًا، ولا تبقى نتيجة بعد تجاوز حد `grace`.

### د) الإدارة

- `TenantProvisioner`: فشل أي خطوة يلغي كل ما سبقها (لا يبقى tenant بلا دومين أو بلا مالك).
- كل عملية على الاشتراك تُنتج صفًا في `subscription_events` فيه `performed_by`، وسجل المحل A يعرض أحداث اشتراكاته فقط.
- التفعيل بعد الدفع يحوّل `trial` إلى `active` في نفس الصف، والتفعيل بعد الإلغاء ينشئ صفًا جديدًا.
- التجديد قبل الانتهاء يحسب المدة من `ends_at`، والتجديد بعد الانتهاء يحسبها من وقت التجديد. التمديد يضيف الأيام المحددة إلى `ends_at` مع إبقاء الحالة المخزّنة. يُرفض التجديد والتمديد للاشتراك بلا تاريخ انتهاء.
- guard `web` لا يفتح `/admin`، وguard `admin` لا يفتح مسارات العميل.

### هـ) اختبارات أضيفت بعد المراجعة

- دومين مخصص غير متحقق منه يُرفض ولا يمكن تعيينه أساسيًا.
- استعلام على موديل بيانات عميل بلا tenant context يرمي `TenantContextMissingException`.
- Filament: لوحة الإدارة تحمّل صفحاتها وطلبات Livewire اللاحقة على `ADMIN_DOMAIN` فقط، و`/admin` على دومين عميل يعطي 404.
- إلغاء أحدث اشتراك لا يُرجع اشتراكًا أقدم، ويُطبَّق عليه سلوك الانتهاء.
- حالة `none` مع override مفعّل: كل شيء مقفول **(مقترح)**.
- المالك يُجبَر على تغيير كلمة المرور عند أول دخول، وإعادة التعيين من اللوحة تعيد العلم.
- نفس البريد في محلين: كل مستخدم يدخل على محله فقط.
- إعادة تعيين كلمة مرور المالك من صفحة المحل A لا تغيّر مالك المحل B، حتى لو كان البريد نفسه.
- لا يمكن إنشاء اشتراك `past_due` أو بـ `starts_at` في المستقبل في المرحلة 1.

### معايير القبول النهائية

1. كل الاختبارات أعلاه تمر.
2. لا مخالفات لقواعد القسم 8 (ابحث عن `withoutGlobalScopes` و`DB::table` و`tenant_id` في `$fillable`).
3. ملف README قصير: التشغيل المحلي، متغيرات البيئة، إنشاء مشرف وعميل تجريبي.
4. ملخص نهائي يذكر كل استنتاج اعتمدت عليه ولم يرد في هذا المستند.

## 11) نقاط مفتوحة (لا يحسمها المنفّذ بنفسه)

لا يوجد بند هنا يمنع بدء الخطوة 1. في كل بند ابدأ بالقيمة المقترحة وسجّلها في ملخصك، ولا تحسم بنفسك ما يغيّر النطاق.

1. **لغة الواجهة** (عربي RTL أو إنجليزي أو الاثنان) **وهل تُبنى واجهة العميل بـ Filament أيضًا**: غير محسومتين. لوحة Super Admin ستكون Filament v5، وواجهة العميل في المرحلة 1 دخول ولوحة بسيطة بـ Blade ما لم يُحسم غير ذلك (مقترح).
2. **أسماء الباقات وأسعارها وقائمة الميزات النهائية** (القسم 6.5): الجدول مقترح فقط.
3. **أيام السماح والتجربة الافتراضية**: المقترح 7 و14.
4. **هل يبقى الـ subdomain الافتراضي للعميل بعد ربط دومينه المخصص؟** المقترح أن يبقى، ويصير الدومين المخصص هو الأساسي.
5. **طريقة SSL للدومينات المخصصة** (Caddy مع On-Demand TLS أو Cloudflare for SaaS): تخص المرحلة 2 ولا تُنفَّذ الآن.
6. **تصميم جداول المخزون والفواتير**: يُحدَّد في مرحلة لاحقة.
7. **هل يعمل override الميزة لما لا يوجد أي اشتراك أصلًا (حالة `none`)؟** المقترح: لا.
8. **استعادة كلمة المرور الذاتية بالبريد**: المقترح تأجيلها بعد المرحلة 1، والمرحلة 1 تكتفي بإعادة التعيين من لوحة Super Admin.
