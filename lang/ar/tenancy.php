<?php

declare(strict_types=1);

return [
    'status' => [
        'active' => 'نشط',
        'trial' => 'تجريبي',
        'suspended' => 'موقوف',
    ],
    'domain_type' => [
        'subdomain' => 'نطاق فرعي',
        'custom' => 'دومين مخصص',
    ],
    'suspended' => 'حساب هذا المتجر موقوف حاليًا.',
    'user_inactive' => 'حسابك غير نشط.',
    'wrong_tenant' => 'بيانات الدخول غير مطابقة لسجلاتنا.',
    'slug_invalid' => 'يجب أن يحتوي المعرّف على حروف إنجليزية صغيرة وأرقام وشرطة فقط، بطول من 3 إلى 32 حرفًا، ولا يبدأ أو ينتهي بشرطة.',
    'slug_reserved' => 'هذا المعرّف محجوز ولا يمكن استخدامه.',
    'slug_taken' => 'هذا النطاق الفرعي مستخدم بالفعل.',
    'cannot_delete_primary_domain' => 'لا يمكن حذف الدومين الأساسي.',
    'cannot_set_unverified_primary' => 'لا يمكن تعيين دومين مخصص غير متحقق منه كدومين أساسي.',
    'cannot_remove_last_owner' => 'يجب بقاء مالك نشط واحد على الأقل في المتجر.',
    'cannot_modify_owner_role' => 'لا يمكن تعديل دور المالك أو حذفه.',
];
