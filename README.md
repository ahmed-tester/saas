# منصة إدارة المحلات التجارية (SaaS)

منصة SaaS متعددة العملاء (Multi-Tenant) لإدارة المحلات التجارية (المخزون والفواتير)، تتيح لكل محل الدخول عبر نطاق فرعي خاص به (`shop1.<base-domain>`) أو دومين مخصص مع عزل كامل للبيانات في قاعدة بيانات واحدة.

## الفئة المستهدفة

- **مشرف المنصة (Super Admin):** صاحب المنصة وفريقه لإدارة المحلات، الدومينات، الباقات، الميزات، الاشتراكات، وإعدادات الفوترة الافتراضية على مستوى المنصة عبر لوحة Filament مستقلة على `/admin`.
- **أصحاب المحلات ومستخدموهم:** يدخل كل متجر إلى مساحته المعزولة بصلاحيات وأدوار مخصصة لكل عميل.

## التقنيات المستخدمة

- PHP `^8.2` وLaravel `12`
- Filament `v5` (لوحة Super Admin)
- `stancl/tenancy` `^3.9` (وضع Single DB)
- `spatie/laravel-permission` `^6.0` (مع تفعيل `teams` باستخدام `tenant_id`)
- MySQL (للتشغيل والاختبار التزامني) وSQLite (للاختبارات السريعة في الذاكرة)

## التشغيل المحلي

1. انسخ ملف البيئة واضبط القيم:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
2. اضبط متغيرات البيئة الأساسية في `.env`:
   - `APP_URL=http://localhost`
   - `APP_BASE_DOMAIN=localhost`
   - `CENTRAL_DOMAINS=localhost,127.0.0.1`
   - `ADMIN_DOMAIN=localhost`
   - إعدادات قاعدة البيانات (`DB_CONNECTION=mysql` وبيانات الاتصال).
3. نفّذ الـ migrations والـ seeders:
   ```bash
   php artisan migrate --seed
   ```
4. أنشئ حساب Super Admin:
   ```bash
   php artisan admin:create
   ```
5. افتح لوحة الإدارة على `http://localhost/admin` لإنشاء محل جديد (سيتم إنشاء النطاق الفرعي وحساب المالك والاشتراك التجريبي في عملية واحدة). للتجربة المحلية، يمكنك الدخول على النطاق الفرعي مثل `http://shop1.localhost/login`.

## تشغيل الاختبارات وفحص التنسيق

```bash
php artisan test
vendor/bin/pint --test
```

## توثيق المشروع

- [خارطة الطريق وحالة التنفيذ (`docs/roadmap.md`)](docs/roadmap.md)
- [المعمارية وعزل البيانات (`docs/architecture.md`)](docs/architecture.md)
- [القرارات والنقاط المفتوحة (`docs/decisions.md`)](docs/decisions.md)
- [خطة تنفيذ المرحلة الأولى](<خطة تنفيذ SaaS إدارة المحلات — المرحلة الأولى (تعليمات للمنفّذ) (2).md>)
