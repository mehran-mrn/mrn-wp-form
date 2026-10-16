# MRN Forms

یک فرم‌ساز حرفه‌ای، سبک، دو‌زبانه و مستقل از صفحه‌ساز برای WordPress.

ویژگی‌های اصلی:

- فرم‌ساز Drag & Drop فارسی و انگلیسی با ۱۴ نوع فیلد و پشتیبانی RTL/LTR
- منطق شرطی، اعتبارسنجی امن، الزام وابسته به خالی‌بودن فیلد دیگر و آپلود فایل
- صندوق ورودی، CSV و وضعیت خوانده‌شده/Spam
- اعلان ایمیلی HTML برای مدیر و تکمیل‌کننده فرم
- شورت‌کد، بلوک Gutenberg و تابع قالب
- طراحی واکنش‌گرا و قابل سفارشی‌سازی با CSS variables
- بدون وابستگی runtime خارجی

مستندات کامل نصب، Merge Tagها و API توسعه‌دهندگان در [readme.txt](readme.txt) قرار دارد.

## توسعه

```bash
composer install
composer test
composer lint
```

حداقل نسخه‌ها: WordPress 6.6 و PHP 8.1.

سازنده: [Mehran Marandi](https://mehranmarandi.ir)

محصولی از [MRNWP](https://mrnwp.ir) · [صفحه افزونه](https://mrnwp.ir/mrnforms) · [کد و گزارش مشکل](https://github.com/mehran-mrn/mrn-wp-form)

بستهٔ انتشار از پوشهٔ `mrn-forms` و دامنهٔ ترجمهٔ `mrn-forms` استفاده می‌کند. نام جدول‌ها، شورت‌کدها و تنظیمات نسخه‌های قبلی حفظ شده‌اند. قبل از فعال کردن این بسته روی نصب قدیمی، نسخهٔ پوشهٔ `mrn-form` را غیرفعال کنید.

ثبت منبع بازدید و ارسال رویدادهای Google Analytics اختیاری و پیش‌فرض خاموش هستند. جزئیات داده‌ها و سرویس خارجی در readme.txt آمده است.

تست رفتار حریم خصوصی در مرورگر: `node tests/test-tracking.js`.

ساخت ZIP انتشار: `powershell -File tools/package.ps1`.

## انتشار در WordPress.org

راهنمای تنظیم دسترسی و انتشار از گیت‌هاب در [docs/releases-fa.md](docs/releases-fa.md) است. Push عادی تست‌ها را اجرا می‌کند؛ انتشار یک Release نهایی، پس از موفق‌شدن تست‌ها، کد و تصاویر را به مخزن وردپرس منتقل می‌کند.
