<p align="center">
    <img width="200" src="uploads/2025/12/logo_dR8gaBtCWZMN6PQh.png" alt="Tarkiz Virtual School Logo"><br>
    <h1 align="center">Tarkiz Virtual School | مدرسة تركيز الافتراضية</h1>
    <p align="center">منصة تعلم افتراضي متكاملة - An Integrated Virtual Learning Platform</p>
</p>

------

## عن المنصة | About The Platform

**Tarkiz Virtual School (مدرسة تركيز الافتراضية)** هي منصة تعليم افتراضي متكاملة تم تطويرها بالاعتماد على نظام **Gibbon** (نظام إدارة مدرسي مفتوح المصدر). تهدف المنصة إلى تقديم تعليم عن بُعد للطلاب السوريين في الخارج وفق المناهج السورية المعتمدة من وزارة التربية السورية.

## رسالة الماجستير | Master's Thesis

هذا المشروع أُعد لتقديمه كجزء من متطلبات رسالة الماجستير في:

**"تصميم وتنفيذ منصة تعلم افتراضي متكاملة لمدرسة تركيز الافتراضية وفق متطلبات وزارة التربية السورية – دراسة حالة في تطوير بيئة تعليمية رقمية سورية"**

**"Design and Implementation of an Integrated Virtual Learning Platform for Tarkiz Virtual School According to the Requirements of the Syrian Ministry of Education – A Case Study in Developing a Syrian Digital Educational Environment"**

## رؤية المدرسة | Vision

نسعى لتحقيق الريادة في التعليم عن بُعد ومساعدة طلابنا على بناء مستقبل متميز ضمن بيئة محفزة تدعمهم لاكتشاف إمكاناتهم الحقيقية، وتعمق الفهم، وتعزز الابتكار لديهم من خلال مناهج دراسية معتمدة وخدمات تعليمية فريدة تقدم عبر برمجيات متطورة تدعم التعليم الافتراضي.

## الرسالة | Mission

رسالتنا تعليمية، وطنية، إنسانية:
- **إنسانية**: نشر المناهج التعليمية والإثرائية لطلابنا في كل أنحاء العالم بأقساط مناسبة تراعي الظروف المعيشية
- **تعليمية**: نشر المناهج السورية للطلاب خارج حدود الوطن عبر برمجيات التعليم عن بُعد المتطورة
- **وطنية**: تعزيز المواطنة لدى طلابنا السوريين في المغترب من خلال تعليم المناهج السورية

## الأهداف | Objectives

1. تقديم تعليم افتراضي مبتكر يلبي احتياجات الطلاب ومتطلبات أولياء الأمور
2. تعزيز مهارات التفكير الناقد والإبداعي والبرمجي لدى الطلاب
3. دعم الطلاب لتحقيق التميز في الجوانب التعليمية والتكميلية
4. تلبية تطلعات الطلاب وأولياء الأمور وتعزيز مبادئ الشفافية والموثوقية

## التراخيص والاعتمادات | Licenses & Accreditations

- مرخصة رسمياً من وزارة التربية السورية بموجب القرار الوزاري رقم 2/2288 (43)/5/4 تاريخ 2/3/2023
- حاصلة على شهادة المطابقة لنظام إدارة الجودة وفق المواصفة الدولية ISO9001:2015

## فريق التطوير | Development Team

تم إنشاء وتطوير المنصة بواسطة فريق AY للبرمجيات:
- **م. بشار ياسر المحمد الخلف** (Eng. Bashar Yasser Al-Mohammed Al-Khalaf)
- **م. دحام ياسر المحمد الخلف** (Eng. Daham Yasser Al-Mohammed Al-Khalaf)

## المتطلبات التقنية | System Requirements

| المتطلب | الإصدار |
|---------|---------|
| PHP | 7.4.0+ |
| MySQL | 5.7+ |
| Apache | (mod_rewrite) |
| الإضافات المطلوبة | gettext, mbstring, curl, zip, xml, gd, intl |

## التثبيت المحلي | Local Installation

```bash
# 1. Clone the repository
git clone https://github.com/dahaamfirst/tarkiz_mcs.git

# 2. Install composer dependencies
composer install

# 3. Create database and import your SQL dump
mysql -u root -e "CREATE DATABASE tarkiz_mcs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root tarkiz_mcs < your_database.sql

# 4. Copy and configure config.php
cp config.example.php config.php
# Edit config.php with your database credentials

# 5. Set up uploads directory permissions
chmod -R 755 uploads/cache
```

## Gibbon Core

This platform is built on **Gibbon** - a flexible, open source school management platform designed to make life better for teachers, students, parents and schools. Gibbon is licensed under GNU General Public License v3.0.

## License

Gibbon is licensed under GNU General Public License v3.0.
Tarkiz Virtual School platform modifications © 2023-2026 AY Software Development Team.
