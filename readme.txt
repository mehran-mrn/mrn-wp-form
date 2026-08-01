=== MRN Form ===
Contributors: mehran-mrn
Tags: form builder, contact form, email notification, entries, rtl, ltr, persian, english
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

فرم‌ساز سبک و حرفه‌ای MRN با رابط فارسی و انگلیسی، پشتیبانی RTL/LTR، صندوق ورودی، منطق شرطی و اعلان ایمیلی.

== Description ==

MRN Form برای سایت‌هایی ساخته شده که یک فرم‌ساز حرفه‌ای می‌خواهند، اما نمی‌خواهند برای نمایش و استایل فرم به صفحه‌ساز یا کتابخانه JavaScript سنگین وابسته باشند.

= امکانات =

* فرم‌ساز Drag & Drop با پیش‌نمایش زنده
* رابط کامل فارسی و انگلیسی با تشخیص خودکار RTL/LTR بر اساس زبان وردپرس
* ۱۴ نوع فیلد: متن، ایمیل، تلفن، عدد، متن بلند، انتخابی، رادیویی، چک‌باکس، تاریخ، فایل، رضایت، پنهان، عنوان و HTML
* عرض‌های واکنش‌گرا، رنگ، فاصله، گردی گوشه، محل برچسب و کلاس CSS اختصاصی
* منطق شرطی برای نمایش فیلدها
* اعتبارسنجی هم‌زمان مرورگر و سرور
* آپلود امن با کنترل پسوند و حجم
* ذخیره ارسال‌ها، وضعیت خوانده‌شده/هرزنامه و خروجی CSV
* دو اعلان آماده: ایمیل مدیر و رسید تکمیل‌کننده
* مسیریابی شرطی اعلان‌ها بر اساس پاسخ هر فیلد
* قالب HTML واکنش‌گرا با رنگ و لوگوی برند
* Merge Tag برای اطلاعات فرم و فیلدها
* ارسال AJAX با fallback کامل بدون JavaScript
* Honeypot، nonce، محدودسازی نرخ و هش کردن IP
* شورت‌کد، بلوک گوتنبرگ و تابع قالب
* RTL/LTR خودکار و دسترس‌پذیری پایه
* بدون وابستگی runtime خارجی

== Installation ==

1. پوشه `mrn-form` را در `wp-content/plugins` قرار دهید.
2. افزونه را فعال کنید.
3. از منوی «فرم‌ها ← فرم جدید» فرم را بسازید.
4. وضعیت را روی «منتشرشده» قرار دهید و ذخیره کنید.
5. شورت‌کد `[mrn_form id="1"]` را در برگه قرار دهید.

== Display without a page builder ==

= Shortcode =

`[mrn_form id="1"]`

یا:

`[mrn_form slug="contact-us"]`

= Theme template =

`<?php mrn_form( 1 ); ?>`

یا با تنظیمات نمایشی:

`<?php mrn_form( 'contact-us', array( 'showTitle' => false ) ); ?>`

= Gutenberg =

بلوک «MRN Form» را اضافه و فرم منتشرشده را انتخاب کنید.

== Merge Tags ==

* `{form_title}` عنوان فرم
* `{form_id}` شناسه فرم
* `{entry_id}` شناسه ارسال
* `{site_name}` نام سایت
* `{site_url}` نشانی سایت
* `{admin_email}` ایمیل پیش‌فرض مدیر
* `{all_fields}` جدول زیبای همه پاسخ‌ها
* `{field:email}` مقدار فیلدی با کلید `email`

چند گیرنده ایمیل را با ویرگول یا `;` جدا کنید.

== Theme customization ==

هر فرم تنظیمات بصری مستقل دارد. برای کنترل کامل‌تر، متغیرهای CSS زیر روی `.mrnf-shell` قابل override هستند:

`--mrnf-primary`, `--mrnf-accent`, `--mrnf-bg`, `--mrnf-text`, `--mrnf-gap`, `--mrnf-radius`

نمونه:

`.my-form { --mrnf-primary: #222; --mrnf-radius: 4px; }`

کلاس `my-form` را در بخش «ظاهر و رفتار ← کلاس CSS سفارشی» ثبت کنید.

== Developer API ==

فیلترها و اکشن‌های اصلی:

* `mrnf_submission_values` — تغییر مقادیر اعتبارسنجی‌شده پیش از ذخیره
* `mrnf_after_entry_created` — پس از ثبت ارسال
* `mrnf_after_submission` — پس از تلاش برای ارسال اعلان‌ها
* `mrnf_after_notification` — پس از ارسال هر اعلان
* `mrnf_notification_results` — تغییر نتیجه کانال‌های اعلان
* `mrnf_rendered_form` — تغییر HTML نهایی فرم

ساختار اعلان‌ها طوری جدا شده که کانال SMS در نسخه بعد بدون تغییر فرم‌ها قابل اضافه شدن باشد.

== Privacy ==

داده فرم‌ها در جدول‌های اختصاصی سایت ذخیره می‌شود. IP خام ذخیره نمی‌شود و تنها hash یک‌طرفه برای کنترل سوءاستفاده ثبت می‌شود. حذف کامل داده در uninstall اختیاری است و باید از تنظیمات فعال شود.

== Changelog ==

= 1.1.1 =

* Preserve a form's stable slug when editing fields, appearance, or notification settings.
* Keep all plugin administration screens inside the standard WordPress content area.

= 1.1.0 =

* افزودن رابط کامل انگلیسی برای مدیریت، فرم‌ساز، فرانت‌اند، اعتبارسنجی و ایمیل‌ها.
* افزودن پشتیبانی خودکار و کامل LTR در کنار RTL.
* ثبت Mehran Marandi و mehranmarandi.ir به‌عنوان سازنده افزونه.

= 1.0.2 =

* دریافت nonce تازه و غیرقابل‌کش پیش از ارسال فرم برای جلوگیری از خطای نشست در صفحات کش‌شده.

= 1.0.1 =

* اصلاح فاصله از سایدبار و حذف اسکرول افقی پنل مدیریت در چیدمان‌های RTL و LTR.

= 1.0.0 =

* انتشار اولیه فرم‌ساز، مدیریت ارسال‌ها، اعلان ایمیل، منطق شرطی و امکانات توسعه‌دهنده.
