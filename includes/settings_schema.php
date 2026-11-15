<?php
/**
 * Site settings, grouped into tabs on the admin Settings page.
 * Tip shown in admin: wrap words in **double asterisks** to make them gold.
 */
return [
    'general' => [
        'label' => 'General', 'icon' => 'fa-solid fa-sliders',
        'fields' => [
            'owner_name'  => ['type' => 'text', 'label' => 'Your full name', 'required' => true],
            'logo_text'   => ['type' => 'text', 'label' => 'Logo text', 'help' => 'Shown in the navbar and footer, e.g. DEV.ELRICH'],
            'nav_cta'     => ['type' => 'text', 'label' => 'Navbar button text', 'help' => 'e.g. Let\'s Talk'],
            'footer_text' => ['type' => 'text', 'label' => 'Footer copyright', 'help' => '<code>{year}</code> is replaced with the current year.'],
            'privacy_url' => ['type' => 'url', 'label' => 'Privacy Policy URL (optional)'],
            'terms_url'   => ['type' => 'url', 'label' => 'Terms of Service URL (optional)'],
            'show_services'     => ['type' => 'checkbox', 'label' => 'Show Services section'],
            'show_projects'     => ['type' => 'checkbox', 'label' => 'Show Projects section'],
            'show_gallery'      => ['type' => 'checkbox', 'label' => 'Show Photo & Video gallery'],
            'show_experience'   => ['type' => 'checkbox', 'label' => 'Show Experience section'],
            'show_testimonials' => ['type' => 'checkbox', 'label' => 'Show Testimonials section'],
        ],
    ],
    'hero' => [
        'label' => 'Hero', 'icon' => 'fa-solid fa-star',
        'fields' => [
            'hero_badge'    => ['type' => 'text', 'label' => 'Status badge', 'help' => 'e.g. Available for new opportunities. Leave empty to hide.'],
            'hero_roles'    => ['type' => 'textarea', 'label' => 'Rotating roles', 'rows' => 4, 'help' => 'One per line. Shown as "I\'m a ___" with a typing animation. Leave empty to hide.'],
            'hero_title'    => ['type' => 'textarea', 'label' => 'Headline', 'rows' => 2, 'help' => 'New line = line break. Wrap words in <code>**stars**</code> to make them gold.'],
            'hero_subtitle' => ['type' => 'textarea', 'label' => 'Sub-headline', 'rows' => 3],
            'hero_image'    => ['type' => 'image', 'label' => 'Portrait', 'help' => 'Best: a PNG with a transparent background (use remove.bg), roughly 800×900.'],
            'stat1_value'   => ['type' => 'text', 'label' => 'Stat 1 value', 'half' => true],
            'stat1_label'   => ['type' => 'text', 'label' => 'Stat 1 label', 'half' => true],
            'stat2_value'   => ['type' => 'text', 'label' => 'Stat 2 value', 'half' => true],
            'stat2_label'   => ['type' => 'text', 'label' => 'Stat 2 label', 'half' => true],
            'stat3_value'   => ['type' => 'text', 'label' => 'Stat 3 value', 'half' => true],
            'stat3_label'   => ['type' => 'text', 'label' => 'Stat 3 label', 'half' => true],
        ],
    ],
    'about' => [
        'label' => 'About', 'icon' => 'fa-solid fa-user',
        'fields' => [
            'about_title'     => ['type' => 'textarea', 'label' => 'Heading', 'rows' => 2, 'help' => 'Wrap words in <code>**stars**</code> to make them gold.'],
            'about_text'      => ['type' => 'textarea', 'label' => 'Bio', 'rows' => 5, 'max' => 3000, 'help' => 'Wrap words in <code>**stars**</code> to make them gold.'],
            'about_points'    => ['type' => 'textarea', 'label' => 'Highlights', 'rows' => 4, 'help' => 'One per line.'],
            'about_image'     => ['type' => 'image', 'label' => 'About photo', 'help' => 'Portrait orientation (4:5) looks best.'],
            'about_signature' => ['type' => 'text', 'label' => 'Signature text on photo', 'help' => 'Leave empty to hide.'],
            'cv_file'         => ['type' => 'file', 'label' => 'CV / Resume (PDF)', 'help' => 'Powers the "Download CV" button. Hidden if empty.'],
            'skills_note'     => ['type' => 'text', 'label' => 'Note under skills grid', 'help' => 'e.g. Always learning new technologies'],
        ],
    ],
    'contact' => [
        'label' => 'Contact', 'icon' => 'fa-solid fa-envelope',
        'fields' => [
            'contact_title'        => ['type' => 'textarea', 'label' => 'Heading', 'rows' => 2, 'help' => 'Wrap words in <code>**stars**</code> to make them gold.'],
            'contact_text'         => ['type' => 'textarea', 'label' => 'Intro text', 'rows' => 2],
            'contact_email'        => ['type' => 'email', 'label' => 'Email'],
            'contact_phone'        => ['type' => 'text', 'label' => 'Phone'],
            'contact_location'     => ['type' => 'text', 'label' => 'Location'],
            'contact_availability' => ['type' => 'text', 'label' => 'Availability'],
            'formspree_id'         => ['type' => 'text', 'label' => 'Formspree form ID (for the GitHub Pages site)', 'max' => 40,
                'help' => 'GitHub Pages can\'t run PHP, so the published site sends the contact form through <a href="https://formspree.io" target="_blank" rel="noopener">Formspree</a> (free). Create a form there and paste the ID from its URL, e.g. <code>xyzabcd</code> from formspree.io/f/<b>xyzabcd</b>. Leave empty to open the visitor\'s email app instead. Then click Publish.'],
        ],
    ],
    'seo' => [
        'label' => 'SEO & Sharing', 'icon' => 'fa-solid fa-magnifying-glass',
        'fields' => [
            'site_url'         => ['type' => 'url', 'label' => 'Live website address', 'help' => 'Where Publish puts your site, e.g. <code>https://kinglong05.github.io/</code>. Used for share links and Google.'],
            'meta_title'       => ['type' => 'text', 'label' => 'Browser tab / Google title'],
            'meta_description' => ['type' => 'textarea', 'label' => 'Meta description', 'rows' => 3, 'help' => 'About 150 characters. Shown in Google results.'],
            'og_image'         => ['type' => 'image', 'label' => 'Social share image', 'help' => '1200×630, shown when your link is shared on Facebook, LinkedIn, Messenger, etc.'],
        ],
    ],
];
