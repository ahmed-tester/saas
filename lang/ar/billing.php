<?php

declare(strict_types=1);

return [
    'period' => [
        'monthly' => 'شهري',
        'yearly' => 'سنوي',
    ],
    'expiry_behavior' => [
        'keep' => 'يبقى مفتوحًا',
        'read_only' => 'للقراءة فقط',
        'lock' => 'مقفل',
    ],
    'subscription_state' => [
        'active' => 'نشط',
        'grace' => 'فترة سماح',
        'expired' => 'منتهي',
        'none' => 'بلا اشتراك',
    ],
    'subscription_status' => [
        'trial' => 'تجريبي',
        'active' => 'نشط',
        'canceled' => 'ملغي',
    ],
    'subscription_event' => [
        'activated' => 'تفعيل',
        'renewed' => 'تجديد',
        'plan_changed' => 'تغيير الباقة',
        'extended' => 'تمديد',
        'canceled' => 'إلغاء',
    ],
    'feature' => [
        'invoicing' => 'الفواتير',
        'inventory' => 'المخزون',
        'data_export' => 'تصدير البيانات',
        'multi_warehouse' => 'تعدد المستودعات',
        'advanced_reports' => 'التقارير المتقدمة',
        'barcode_printing' => 'طباعة الباركود',
        'max_users' => 'الحد الأقصى للمستخدمين',
        'max_products' => 'الحد الأقصى للمنتجات',
    ],
    'feature_type' => [
        'boolean' => 'منطقي (نعم/لا)',
        'limit' => 'حد رقمي',
    ],
    'feature_locked' => 'هذه الميزة مقفلة. يرجى ترقية الباقة.',
    'feature_read_only' => 'هذه الميزة في وضع القراءة فقط بسبب انتهاء الاشتراك.',
    'user_limit_reached' => 'تم الوصول إلى الحد الأقصى لعدد المستخدمين في باقتك.',
    'feature_not_available_for_tenant' => 'إحدى الصلاحيات المختارة تتبع ميزة غير متاحة لهذا المتجر.',
    'cannot_renew_endless' => 'لا يمكن تجديد اشتراك نشط بلا تاريخ انتهاء.',
    'cannot_extend_endless' => 'لا يمكن تمديد اشتراك نشط بلا تاريخ انتهاء.',
    'cannot_extend_canceled' => 'لا يمكن تمديد اشتراك ملغي.',
    'cannot_renew_non_active' => 'يمكن تجديد الاشتراكات النشطة ذات تاريخ انتهاء فقط.',
    'cannot_activate_active' => 'الاشتراك نشط بالفعل.',
    'invalid_starts_at' => 'لا يمكن أن يكون تاريخ بدء الاشتراك في المستقبل.',
    'invalid_past_due' => 'حالة past_due غير مدعومة في المرحلة الأولى.',
    'invalid_extend_days' => 'يجب أن يكون عدد أيام التمديد رقمًا صحيحًا موجبًا.',
];
