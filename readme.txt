=== MRN Forms ===
Contributors: mrnwp
Tags: form builder, contact form, conditional logic, email notifications, rtl
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.4.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight bilingual form builder with drag and drop, conditional logic, entries, file uploads, and email notifications.

== Description ==

MRN Forms lets you build and style WordPress forms without a page builder. It includes a visual editor, an entry inbox, configurable email notifications, and Persian/English interfaces with automatic RTL/LTR layouts.

[Product website](https://mrnwp.ir/mrnforms) | [MRNWP](https://mrnwp.ir) | [Source and issue tracker](https://github.com/mehran-mrn/mrn-wp-form)

= Features =

* Drag-and-drop form builder with live preview and fourteen field types.
* Conditional visibility and fields required when another field is empty.
* Client and server validation, honeypot protection, form nonces, and rate limits.
* Configurable field widths, labels, colors, spacing, and custom CSS classes.
* Entry inbox, read/spam statuses, and CSV exports.
* Administrator notifications and respondent receipts with HTML templates and merge tags.
* Conditional notification recipients.
* Shortcodes, a dynamic Gutenberg block, and a theme function.
* JavaScript submission with a standard POST fallback.
* Optional traffic attribution and events for an existing Google Analytics installation. Both are off by default.
* No paid license, external account, or external runtime library is required for the form builder.

MRN Forms is developed by [Mehran Marandi](https://mehranmarandi.ir) as a product of MRNWP.

= فارسی =

MRN Forms برای سایت‌هایی ساخته شده که یک فرم‌ساز حرفه‌ای می‌خواهند، اما نمی‌خواهند برای نمایش و استایل فرم به صفحه‌ساز یا کتابخانه JavaScript سنگین وابسته باشند.

= امکانات =

* فرم‌ساز Drag & Drop با پیش‌نمایش زنده
* رابط کامل فارسی و انگلیسی با تشخیص خودکار RTL/LTR بر اساس زبان وردپرس
* ۱۴ نوع فیلد: متن، ایمیل، تلفن، عدد، متن بلند، انتخابی، رادیویی، چک‌باکس، تاریخ، فایل، رضایت، پنهان، عنوان و HTML
* عرض‌های واکنش‌گرا، رنگ، فاصله، گردی گوشه، محل برچسب و کلاس CSS اختصاصی
* منطق شرطی برای نمایش فیلدها
* اعتبارسنجی هم‌زمان مرورگر و سرور
* آپلود امن با کنترل پسوند و حجم
* ثبت اختیاری منبع ورود، کمپین، صفحه فرود و ارجاع‌دهنده برای هر ارسال؛ پیش‌فرض خاموش
* رویدادهای اختیاری GA4 برای کلیک تماس، واتساپ و ارسال موفق فرم؛ پیش‌فرض خاموش
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

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin, or copy the `mrn-forms` folder into `/wp-content/plugins/`.
2. Activate MRN Forms.
3. Open Forms > New Form, add fields, and publish the form.
4. Add the MRN Forms block to a page, or use `[mrn_form id="1"]` with your form ID.

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

بلوک «MRN Forms» را اضافه و فرم منتشرشده را انتخاب کنید.

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

Form definitions, submitted values, and delivery logs are stored in this site's database. Entry metadata includes submission time, the logged-in user ID when present, browser user agent, referring URL, and a keyed one-way IP hash. Raw IP addresses are not saved with entries; the hash is used for abuse control. Notifications use WordPress `wp_mail()` and the site's configured mail transport, and are sent to configured recipients. The plugin does not send entries or usage statistics to MRNWP or the developer.

Traffic attribution and analytics events are separate opt-in settings under Forms > Settings, and are off by default. Configure privacy notices and any required visitor consent before enabling them.

When attribution is enabled, source, medium, campaign, term, content, landing page, referrer, and capture time are saved in browser session storage and with submissions on this site. Attribution page URLs omit query strings and fragments. These are browser-supplied reporting values, not trusted identity information.

When analytics events are enabled and the site already provides a `gtag` function, phone-click, WhatsApp-click, and successful-submission events are passed to that function. Events include the interaction type, page URL without query parameters, form ID for a submission, and traffic source/medium when attribution is enabled. Form answers, phone numbers, email addresses, and clicked-link text are not included. MRN Forms does not install Google scripts. The site's existing analytics configuration controls delivery and consent, and Google may collect additional information under that configuration.

Optional service: [Google Analytics](https://marketingplatform.google.com/about/analytics/), [Terms](https://marketingplatform.google.com/about/analytics/terms/us/), [Privacy policy](https://policies.google.com/privacy).

داده‌ها روی سایت شما ذخیره می‌شوند. ثبت منبع بازدید و رویدادهای گوگل اختیاری و پیش‌فرض خاموش هستند. پیش از فعال‌سازی، اطلاع‌رسانی حریم خصوصی و رضایت لازم را در سایت تنظیم کنید. حذف داده‌ها هنگام uninstall اختیاری است.

== Frequently Asked Questions ==

= Does it support Persian and English? =

Yes. The plugin includes both interfaces and follows the WordPress locale for RTL/LTR layouts. Other languages can use WordPress translations.

= Will the rename change my existing forms? =

Database tables, settings keys, shortcodes, REST routes, and the block name are retained. If you previously installed the development plugin in a `mrn-form` folder, deactivate that copy before activating the new `mrn-forms` package. Do not uninstall the old copy with its delete-data option enabled when you want to keep your forms.

= How are uploads stored? =

Allowed files are stored through the WordPress upload system and registered as private attachments. A private attachment does not make its underlying file URL private: anyone who knows that URL may be able to access it. Avoid collecting confidential documents unless your site also protects uploaded files.

= What happens when I uninstall the plugin? =

Data is retained by default. Enable the delete-data setting before uninstalling only when you want to permanently remove forms, entries, email logs, and settings. Media attachments and uploaded files are not removed by that setting.

= Where is the source code? =

PHP, JavaScript, and CSS sources are included in the plugin. No build step is required. The development repository is linked above. Code and artwork are distributed under GPLv2 or later.

== Screenshots ==

1. Visual form builder with live preview and configurable fields.
2. Entry inbox with traffic source, status, and export controls.
3. Email settings and optional attribution and analytics controls.

== Changelog ==

= 1.4.3 =
* Correct the WordPress.org contributor username to mrnwp.
* Use WordPress's default bottom placement for the top-level Forms menu.

= 1.4.2 =
* Prevent spreadsheet formula injection in CSV exports, including submitted answers and attribution fields.
* Keep database error details out of public submission responses.
* Include the complete GPL version 2 license text with the installation package.

= 1.4.1 =
* Add the `mrnf_submission_response` filter so an integration can send one submission to its own next step, such as payment.
* Add the `mrnf_entry_detail_aside` action for integration panels on the entry screen.

= 1.4.0 =
* Update the plugin name to MRN Forms and add the product logo.
* Add product, MRNWP, and developer links.
* Match the translation domain and release folder to mrn-forms while retaining stored forms and public APIs.
* Make attribution and existing-GA analytics events opt-in and off by default.
* Exclude contact numbers and link text from analytics events.
* Improve directory documentation and prepare a clean installation package.
* Fix fresh submission nonces when WordPress uses plain permalinks.

= 1.3.0 =
* Capture first-touch source, medium, campaign, landing page, and external referrer with each new entry.
* Show attribution in the inbox, entry details, and CSV exports.
* Send GA4 events for phone clicks, WhatsApp clicks, and successful MRN Forms submissions.

= 1.2.0 =
* Add a requirement-mode control to the form builder: optional, always required, or required when another field is empty.
* Add an alternative-field selector with safe reference cleanup when fields are renamed or deleted.
* Show conditional required indicators dynamically on frontend forms.

= 1.1.2 =
* Add cross-field required-without validation for text and upload alternatives.
* Display successful frontend submissions as accessible, dismissible toast notifications.

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

== Upgrade Notice ==

= 1.4.0 =
Attribution and analytics events are now off by default. Enable the features you need under Forms > Settings after configuring privacy notices and consent.
