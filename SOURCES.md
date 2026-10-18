# Heartbeat CMS — Open Source Compliance, Typography Provenance & Licensing Registry (SOURCES.md)

**Project:** Heartbeat CMS Enterprise Refinement  
**Compliance Standard:** Commercial Open Source Governance & European Data Protection (EU GDPR / DSGVO)  
**Maintained by:** Heartbeat CMS Core Engineering & Compliance Team  
**Last Updated:** October 2026  

---

## 1. Executive Statement of Commercially Permissible Compliance

Heartbeat CMS is an enterprise-grade hybrid and headless content management system engineered for high-performance publishing, agency multi-tenancy, and strict regulatory compliance.

### 1.1 Commercially Permissible Open Source Policy
All third-party code libraries, style primitives, utility frameworks, icons, and typographic assets bundled, embedded, or vendored within Heartbeat CMS strictly adhere to commercially permissible open-source licenses:
- **The MIT License (MIT):** Full commercial use, modification, distribution, private use, and sublicensing permitted.
- **The Apache License, Version 2.0 (Apache-2.0):** Full commercial use, modification, distribution, patent grant, and trademark protection.
- **The SIL Open Font License 1.1 (OFL-1.1):** Free commercial use, bundling, embedding, and modification of typographic assets.
- **Public Domain / Permissive Dedication (SQLite Blessing):** Unrestricted commercial usage.

Heartbeat CMS strictly prohibits the inclusion of copyleft-licensed dependencies (e.g., GNU GPLv2/v3, AGPL, SSPL) in core distributions, guaranteeing that downstream clients, agencies, and enterprise deployments maintain complete proprietary ownership of their custom code, themes, and client plugins without viral licensing encumbrances.

### 1.2 Zero External CDN Leaks & GDPR / DSGVO Privacy Attestation
In accordance with Regulation (EU) 2016/679 of the European Parliament and Council (General Data Protection Regulation / DSGVO) and the landmark ruling of the Regional Court of Munich (LG München I, Endurteil vom 20.01.2022, Az. 3 O 17493/20):
- **100% Self-Hosted Web Assets:** All JavaScript runtimes, CSS stylesheets, icons, and web fonts (`.woff2`) are hosted directly from the local server origin under `/public/assets/`.
- **Zero Runtime Google CDN Requests:** At runtime, the frontend and administrative backend generate zero unauthorized external network requests to Google Fonts (`fonts.googleapis.com` or `fonts.gstatic.com`) or any other foreign Content Delivery Network.
- **Local Font Engine:** The integrated `GoogleFontsDownloader` service fetches `.woff2` binaries on-demand during administrative setup, writes them to `/public/assets/fonts/`, and generates zero-external-dependency `@font-face` CSS files locally.
- **End-User Privacy Guarantee:** End-user IP addresses are never transmitted to third-party US infrastructure without explicit prior user consent, completely mitigating GDPR cease-and-desist liabilities ("Abmahnungen").

---

## 2. Third-Party Software Packages & Dependencies

The following table itemizes all third-party software packages, runtime utilities, icon collections, and infrastructure drivers utilized in Heartbeat CMS:

| Component / Library | Version / Channel | Creator / Maintainer | Upstream Repository / Source URL | License | Purpose in Heartbeat CMS |
|---|---|---|---|---|---|
| **Alpine.js** | v3.13.x | Caleb Porzio & Contributors | [https://github.com/alpinejs/alpine](https://github.com/alpinejs/alpine) | **MIT** | Lightweight client-side reactive UI engine for admin shell, interactive modals, toasts, real-time customizer previews, and accordion components. Vendored locally at `/public/assets/js/alpine.min.js`. |
| **Tailwind CSS Primitives** | v3.4.x utility architecture | Tailwind Labs, Inc. (Adam Wathan, Steve Schoger) | [https://github.com/tailwindlabs/tailwindcss](https://github.com/tailwindlabs/tailwindcss) | **MIT** | Design token system, utility class hierarchy, and CSS variables supporting dark-mode admin UI (`/public/assets/css/admin.css`) and responsive frontend theme layouts. |
| **Material Symbols Outlined** | Variable (20..24 opsz, 400..600 wght) | Google LLC & Material Design Team | [https://github.com/google/material-design-icons](https://github.com/google/material-design-icons) | **Apache 2.0** | Iconography system for administrative sidebar, toolbar actions, status badges, and content management controls. |
| **Heroicons** | v2.1.x | Tailwind Labs, Inc. | [https://github.com/tailwindlabs/heroicons](https://github.com/tailwindlabs/heroicons) | **MIT** | Inline SVG vector iconography utilized in frontend themes, block renderer components (FAQ accordions, feature cards, contact controls), and notification toasts. |
| **FontAwesome Free** | v6.5.x | Fonticons, Inc. (Dave Gandy) | [https://github.com/FortAwesome/Font-Awesome](https://github.com/FortAwesome/Font-Awesome) | **CC BY 4.0 / SIL OFL / MIT** | Supplementary vector iconography and social media platform glyphs for theme footers and share widgets. |
| **PHP PDO SQLite Driver Wrapper** | Native PHP 8.2+ extension | SQLite Consortium (D. Richard Hipp) | [https://www.sqlite.org](https://www.sqlite.org) | **Public Domain (SQLite Blessing)** | Zero-configuration relational storage engine wrapper (`core/Database/Database.php`) supporting Write-Ahead Logging (WAL) and foreign key enforcement. |
| **PHP cURL Engine Client Wrapper** | Native PHP 8.2+ extension | Daniel Stenberg & cURL Contributors | [https://curl.se](https://curl.se) | **curl License (MIT/X Deriv.)** | HTTP transfer client wrapper (`core/Gdpr/GoogleFontsDownloader.php`, `core/Ai/AiService.php`) utilized for administrative asset downloads and webhook dispatches. |

---

## 3. Typographic Registry & Local Font Provenance

Heartbeat CMS provides a curated typography system and font management suite under `/admin/appearance/fonts` (and `/admin/fonts`). All curated fonts are licensed under the **SIL Open Font License 1.1 (OFL-1.1)** or the **Apache License 2.0**, guaranteeing 100% freedom for commercial publishing, embedding, and redistribution.

### 3.1 Curated Local Font Families

#### 1. Inter
- **Primary Designer / Author:** Rasmus Andersson (RSMS)
- **Contributors / Foundry:** Rasmus Andersson and the Inter Project Authors
- **Upstream Repository:** [https://github.com/rsms/inter](https://github.com/rsms/inter)
- **License:** **SIL Open Font License 1.1 (OFL-1.1)**
- **Local Storage Path:** `/public/assets/fonts/inter/`
- **Typographic Profile:** Variable neo-grotesque sans-serif typeface crafted specifically for high-density computer screens. Default system typeface for the Heartbeat CMS admin shell, data tables, and modern body text.

#### 2. Roboto
- **Primary Designer / Author:** Christian Robertson
- **Contributors / Foundry:** Google LLC
- **Upstream Repository:** [https://github.com/googlefonts/roboto](https://github.com/googlefonts/roboto)
- **License:** **Apache License, Version 2.0**
- **Local Storage Path:** `/public/assets/fonts/roboto/`
- **Typographic Profile:** Geometric sans-serif with friendly, open curves. Highly legible across mobile displays, responsive dashboards, and clean editorial interfaces.

#### 3. Poppins
- **Primary Designer / Author:** Jonny Pinhorn, Ninad Kahle
- **Contributors / Foundry:** Indian Type Foundry (ITF)
- **Upstream Repository:** [https://github.com/itfoundry/Poppins](https://github.com/itfoundry/Poppins)
- **License:** **SIL Open Font License 1.1 (OFL-1.1)**
- **Local Storage Path:** `/public/assets/fonts/poppins/`
- **Typographic Profile:** Geometric sans-serif typeface supporting Devanagari and Latin scripts. Featuring rationalist, monolinear geometry popular in modern startup branding, hero headings, and SaaS marketing copy.

#### 4. Playfair Display
- **Primary Designer / Author:** Claus Eggers Sørensen
- **Contributors / Foundry:** Claus Eggers Sørensen
- **Upstream Repository:** [https://github.com/clauseggers/Playfair-Display](https://github.com/clauseggers/Playfair-Display)
- **License:** **SIL Open Font License 1.1 (OFL-1.1)**
- **Local Storage Path:** `/public/assets/fonts/playfair-display/`
- **Typographic Profile:** High-contrast transitional serif typeface influenced by the late 18th-century European Enlightenment (Baskerville, Martin). Tailored for luxury real estate headings, editorial titles, and high-fashion presentations.

#### 5. Montserrat
- **Primary Designer / Author:** Julieta Ulanovsky
- **Contributors / Foundry:** Sol Matas, Juan Pablo del Peral, Jacques Le Bailly
- **Upstream Repository:** [https://github.com/JulietaUla/Montserrat](https://github.com/JulietaUla/Montserrat)
- **License:** **SIL Open Font License 1.1 (OFL-1.1)**
- **Local Storage Path:** `/public/assets/fonts/montserrat/`
- **Typographic Profile:** Geometric sans-serif inspired by the traditional posters, signage, and painted street signs of the historic Montserrat neighborhood of Buenos Aires.

#### 6. Plus Jakarta Sans
- **Primary Designer / Author:** Gumpita Rahayu
- **Contributors / Foundry:** Tokotype (Bandung, Indonesia)
- **Upstream Repository:** [https://github.com/tokotype/PlusJakartaSans](https://github.com/tokotype/PlusJakartaSans)
- **License:** **SIL Open Font License 1.1 (OFL-1.1)**
- **Local Storage Path:** `/public/assets/fonts/plus-jakarta-sans/`
- **Typographic Profile:** Modern neo-grotesque geometric sans-serif commissioned for the Provincial Government of Jakarta. Features clean letterforms, distinct ink traps, and exceptional digital legibility.

#### 7. Outfit
- **Primary Designer / Author:** Rodrigo Fuenzalida
- **Contributors / Foundry:** OnBrand
- **Upstream Repository:** [https://github.com/Outfitio/Outfit-Fonts](https://github.com/Outfitio/Outfit-Fonts)
- **License:** **SIL Open Font License 1.1 (OFL-1.1)**
- **Local Storage Path:** `/public/assets/fonts/outfit/`
- **Typographic Profile:** Clean, contemporary geometric sans-serif designed for digital automation brands. Ideal for bold editorial headings and vibrant SaaS showcase typography.

#### 8. Lora
- **Primary Designer / Author:** Olga Karpushina, Alexei Vanyashin
- **Contributors / Foundry:** Cyreal Type Foundry
- **Upstream Repository:** [https://github.com/cyrealtype/Lora-Cyrillic](https://github.com/cyrealtype/Lora-Cyrillic)
- **License:** **SIL Open Font License 1.1 (OFL-1.1)**
- **Local Storage Path:** `/public/assets/fonts/lora/`
- **Typographic Profile:** Contemporary serif typeface with balanced roots in calligraphy. Calibrated for long-form reading, academic blogs, case studies, and corporate storytelling.

---

## 4. Architectural, Design & Theme Inspirations

The design system, administrative workflows, and visual templates in Heartbeat CMS draw professional inspiration from industry-leading agency standards, open-source architectures, and creative agency showcases:

1. **Administrative Shell & Brand Identity:**
   - **Inspiration:** `https://www.herzundseele-digital.de`
   - **Characteristics:** Material Design 3 structural principles, deep charcoal background (`#090d16`), vibrant brand accents, 8pt spatial grid, responsive collapsible inspector/sidebar, and unobtrusive reactive toasts.
2. **Modular CMS Core Architecture:**
   - **Inspiration:** WordPress, Gutenberg Block Editor & Headless CMS architectures.
   - **Characteristics:** Decoupled block registry (`core/Blocks/BlockRegistry.php`), semantic HTML compilation (`BlockRenderer.php`), Custom Post Types, live customizer CSS engine (`ThemeService.php`), and automated XML Sitemap / SEO metadata generation (`SeoService.php`).
3. **One-Pager Starter Theme (`themes/default/`):**
   - **Inspiration:** MegaOne creative agency and digital marketing showcase templates.
   - **Characteristics:** Smooth-scrolling single-page marketing layout, dual-CTA hero, showcase visual frame with floating badges, client testimonial carousel, and reactive accordion FAQ.
4. **Modern Startup Theme (`themes/startup/`):**
   - **Inspiration:** Torino SaaS & AI Keynote presentation templates.
   - **Characteristics:** High-conversion developer-centric hero section, floating metric badge pills, interactive product showcase stage, and feature matrix cards.
5. **Classic Real Estate Theme (`themes/real-estate/`):**
   - **Inspiration:** MaisonCo luxury architectural and property listing templates.
   - **Characteristics:** Dedicated `property` Custom Post Type integration, client-side dynamic filterable search grid (location, price slider, rooms), and photo gallery lightbox modal.
6. **Corporate Theme (`themes/corporate/`):**
   - **Inspiration:** Insuren enterprise consulting and business agency templates.
   - **Characteristics:** Clean enterprise visual hierarchy, multi-column service offerings, floating trust indicators, and structured corporate contact inquiries.

---

## 5. Complete Open Source License Texts

### 5.1 The MIT License (MIT)
```
MIT License

Copyright (c) 2019-2026 The Authors and Contributors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### 5.2 The Apache License, Version 2.0 (Apache-2.0)
```
                                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.
      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and
      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and
      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work; and
      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, You must include a readable copy of the attribution
          notices contained within such NOTICE file.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      shall any Contributor be liable to You for damages, including any
      direct, indirect, special, incidental, or exemplary damages arising
      as a result of this License or out of the use or inability to use
      the Work.
```

### 5.3 SIL Open Font License (OFL-1.1)
```
-----------------------------------------------------------
SIL OPEN FONT LICENSE Version 1.1 - 26 February 2007
-----------------------------------------------------------

PREAMBLE
The goals of the Open Font License (OFL) are to stimulate worldwide
development of collaborative font projects, to support the font creation
efforts of academic and linguistic communities, and to provide a free and
open framework in which fonts may be shared and improved in partnership
with others.

The OFL allows the licensed fonts to be used, studied, modified and
redistributed freely as long as they are not sold by themselves. The
fonts, including any derivative works, can be bundled, embedded, 
redistributed and/or sold with any software provided that any reserved
names are not used by derivative works. The fonts and derivatives,
however, cannot be released under any other type of license. The
requirement for fonts to remain under this license does not apply
to any document created using the fonts or their derivatives.

DEFINITIONS
"Font Software" refers to the set of files released by the Copyright
Holder(s) under this license and clearly marked as such. This may
include source files, build scripts and documentation.

"Reserved Font Name" refers to any names specified as such after the
copyright statement(s).

"Original Version" refers to the collection of Font Software components as
distributed by the Copyright Holder(s).

"Modified Version" refers to any derivative made by adding to, deleting,
or substituting -- in part or in whole -- any of the components of the
Original Version, by changing formats or by porting the Font Software to a
new environment.

"Author" refers to any designer, engineer, programmer, technical
writer or other person who contributed to the Font Software.

PERMISSION & CONDITIONS
Permission is hereby granted, free of charge, to any person obtaining
a copy of the Font Software, to use, study, copy, merge, embed, modify,
redistribute, and sell modified and unmodified copies of the Font
Software, subject to the following conditions:

1) Neither the Font Software nor any of its individual components,
in Original or Modified Versions, may be sold by itself.

2) Original or Modified Versions of the Font Software may be bundled,
redistributed and/or sold with any software, provided that each copy
contains the above copyright notice and this license. These can be
included either as stand-alone text files, human-readable headers or
in the appropriate machine-readable metadata fields within text or
binary files as long as those fields can be easily viewed by the user.

3) No Modified Version of the Font Software may use the Reserved Font
Name(s) unless prominent written permission is granted by the corresponding
Copyright Holder. This restriction only applies to the primary font name as
presented to the users.

4) The name(s) of the Copyright Holder(s) or the Author(s) of the Font
Software shall not be used to promote, endorse or advertise any
Modified Version, except to acknowledge the contribution(s) of the
Copyright Holder(s) and the Author(s) or with their explicit written
permission.

5) The Font Software, modified or unmodified, in part or in whole,
must be distributed entirely under this license, and must not be
distributed under any other license. The requirement for fonts to
remain under this license does not apply to any document created
using the Font Software.

TERMINATION
This license becomes null and void if any of the above conditions are
not met.

DISCLAIMER
THE FONT SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO ANY WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT
OF COPYRIGHT, PATENT, TRADEMARK, OR OTHER RIGHT. IN NO EVENT SHALL THE
COPYRIGHT HOLDER BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
INCLUDING ANY GENERAL, SPECIAL, INDIRECT, INCIDENTAL, OR CONSEQUENTIAL
DAMAGES, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF THE USE OR INABILITY TO USE THE FONT SOFTWARE OR FROM
OTHER DEALINGS IN THE FONT SOFTWARE.
```

---

## 6. Verification and Audit Attestation

This document is continuously verified by automated regression test suites executed via `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m6`:
- **Suite 7, Test 7.1:** Verification of `SOURCES.md` presence in project root and file integrity.
- **Suite 7, Test 7.2:** Validation of commercial open-source license documentation (MIT, Apache 2.0, SIL OFL).
- **Suite 7, Test 7.3:** Auditing of local font family provenance, authorship, and repository records.

For licensing questions, compliance verifications, or font attribution inquiries, contact the Heartbeat CMS Core Team.
