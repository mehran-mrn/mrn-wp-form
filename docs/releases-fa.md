# انتشار MRN Forms در WordPress.org

مخزن کد: https://github.com/mehran-mrn/mrn-wp-form

صفحه افزونه: https://wordpress.org/plugins/mrn-forms/

## راه‌اندازی اولیه

در [تنظیمات Secrets مخزن](https://github.com/mehran-mrn/mrn-wp-form/settings/secrets/actions) دو بار **New repository secret** را بزنید و این موارد را اضافه کنید:

| Name | Secret |
| --- | --- |
| `SVN_USERNAME` | `mrnwp` |
| `SVN_PASSWORD` | رمز مخصوص SVN وردپرس |

رمز را در [تنظیمات WordPress.org](https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password) بسازید. این مقدار را فقط در فرم Secret گیت‌هاب وارد کنید.

## انتشار نسخه

1. کد نهایی را در شاخه `master` قرار دهید و منتظر موفق‌شدن **Plugin checks** در تب Actions بمانید.
2. شماره نسخه را در سربرگ `mrn-form.php`، ثابت `MRNF_VERSION` و `Stable tag` در `readme.txt` یکسان کنید. تغییرات نسخه را در Changelog بنویسید. برای هر انتشار جدید شماره نسخه را افزایش دهید.
3. تصاویر مخزن وردپرس را در `.wordpress-org` نگه دارید. این پوشه با `assets` داخل افزونه فرق دارد و وارد ZIP نصب نمی‌شود.
4. [صفحه Releases](https://github.com/mehran-mrn/mrn-wp-form/releases) را باز کنید و **Draft a new release** را بزنید.
5. در **Choose a tag** شماره نسخه، مثلاً `1.4.3`، را وارد و گزینه ساخت tag جدید را انتخاب کنید. Target باید `master` باشد.
6. عنوان را `MRN Forms 1.4.3` بگذارید و توضیح تغییرات را وارد کنید. گزینه **Set as a pre-release** خاموش باشد.
7. **Publish release** را بزنید.
8. در [تب Actions](https://github.com/mehran-mrn/mrn-wp-form/actions)، اجرای **Publish to WordPress.org** را باز کنید. پس از تست و ساخت بسته، کد در `trunk`، نسخه در `tags/1.4.3` و تصاویر در `assets` مخزن SVN ثبت می‌شوند.

Push عادی و Pull Request فقط تست و ساخت ZIP را اجرا می‌کنند. انتشار عمومی فقط با Publish کردن Release نهایی انجام می‌شود. Draft و Pre-release در وردپرس منتشر نمی‌شوند.

## ساخت بسته

`tools/package.ps1` تنها فایل‌های اجرایی مجاز را در ZIP قرار می‌دهد. پیش از انتقال، `tools/prepare-release.py` شماره نسخه Release، سربرگ، ثابت نسخه، Stable tag، Text Domain و سازنده WordPress.org را بررسی می‌کند. فایل‌های توسعه، تست، مستندات، پوشه Git و تنظیمات انتشار به مخزن وردپرس منتقل نمی‌شوند. فقط نام و رمز SVN در مرحله انتقال در دسترس هستند.

## خطای انتشار

- **Set SVN_USERNAME…**: نام دو Secret و مقادیر آن‌ها را بررسی کنید. نام کاربری باید دقیقاً `mrnwp` باشد.
- **Release tag must match…**: tag، سربرگ، ثابت نسخه و Stable tag را با هم تطبیق دهید. کد منتشرشده در یک tag قبلی را عوض نکنید؛ نسخه جدید بسازید.
- **Version … was already published**: نسخه قبلاً در SVN وجود دارد و بازنویسی نمی‌شود. برای تغییر کد، نسخه جدید لازم است.
- خطای موقت اتصال: پس از رفع مشکل، در همان اجرای Actions گزینه **Re-run failed jobs** را بزنید.

این روند به نصب SVN روی کامپیوتر نیاز ندارد؛ ابزار انتقال روی runner گیت‌هاب اجرا می‌شود. پردازش صفحه و فایل دانلود وردپرس پس از commit ممکن است مدتی طول بکشد.

## منابع

- [راهنمای انتشار SVN وردپرس](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
- [ابزار انتشار 10up](https://github.com/10up/action-wordpress-plugin-deploy)
- [Secrets در GitHub Actions](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets)
