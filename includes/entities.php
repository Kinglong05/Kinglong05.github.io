<?php
/**
 * Defines every list-type content section. The admin "manage" page builds its
 * list, form, validation and saving from this config. Add a field here (and a
 * matching DB column) and it shows up in the admin automatically.
 *
 * Field types: text, textarea, url, number, checkbox, image, icon, select
 */
return [
    'projects' => [
        'label' => 'Projects', 'singular' => 'Project', 'icon' => 'fa-solid fa-briefcase',
        'columns' => ['image' => 'Image', 'title' => 'Title', 'tags' => 'Tags', 'is_featured' => 'Featured'],
        'fields' => [
            'title'       => ['type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 150],
            'description' => ['type' => 'textarea', 'label' => 'Short description', 'max' => 600],
            'tags'        => ['type' => 'text', 'label' => 'Tech tags', 'help' => 'Comma separated, e.g. React, Node.js, MongoDB'],
            'image'       => ['type' => 'image', 'label' => 'Screenshot', 'help' => '16:10 ratio looks best (e.g. 1280×800).'],
            'live_url'    => ['type' => 'url', 'label' => 'Live demo URL'],
            'repo_url'    => ['type' => 'url', 'label' => 'GitHub / source URL'],
            'is_featured' => ['type' => 'checkbox', 'label' => 'Featured (shown before clicking "View all projects")', 'default' => 1],
        ],
    ],
    'gallery' => [
        'label' => 'Photo & Video', 'singular' => 'Gallery item', 'icon' => 'fa-solid fa-images',
        'columns' => ['image' => 'Image', 'title' => 'Title', 'category' => 'Category', 'media_type' => 'Type', 'layout' => 'Size'],
        'fields' => [
            'title'      => ['type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 150],
            'category'   => ['type' => 'select', 'label' => 'Category', 'half' => true, 'options' => GALLERY_CATEGORIES],
            'media_type' => ['type' => 'select', 'label' => 'Type', 'half' => true, 'options' => ['photo' => 'Photo', 'video' => 'Video']],
            'video_url'  => ['type' => 'url', 'label' => 'Link (Facebook, YouTube, Vimeo, Google Drive or .mp4)', 'max' => 500,
                'help' => '<b>Facebook:</b> open your post, click <i>Share → Copy link</i> (or the video\'s <i>⋯ → Copy link</i>) and paste it here. The post must be set to <b>Public</b>. '
                        . 'With no thumbnail uploaded, the card shows the live Facebook player and plays right on the card. Facebook photo posts work too.'],
            'video_file' => ['type' => 'video', 'label' => 'Or upload the video file (MP4)',
                'help' => 'Plays right on your site, even when Facebook blocks its player (e.g. videos with copyrighted music). Max ' . max_video_mb() . ' MB. Tip: export 1080p H.264 MP4. A shorter clip or highlight reel keeps it small.'],
            'image'      => ['type' => 'image', 'label' => 'Photo / thumbnail (optional for links)', 'help' => 'For photos: the photo itself (up to ' . MAX_IMAGE_MB . ' MB, ~2000px wide). For links: optional cover image; the video then plays in a full-screen pop-up. YouTube thumbnails are automatic.'],
            'location'   => ['type' => 'text', 'label' => 'Location (optional)', 'half' => true, 'max' => 150],
            'layout'     => ['type' => 'select', 'label' => 'Tile size in grid', 'half' => true, 'options' => ['normal' => 'Normal', 'wide' => 'Wide (2 columns)', 'tall' => 'Tall (2 rows)', 'big' => 'Big (2×2)']],
            'description'=> ['type' => 'textarea', 'label' => 'Caption (optional)', 'rows' => 2, 'max' => 400],
        ],
    ],
    'skills' => [
        'label' => 'Skills & Stack', 'singular' => 'Skill', 'icon' => 'fa-solid fa-layer-group',
        'columns' => ['icon_class' => 'Icon', 'name' => 'Name'],
        'fields' => [
            'name'       => ['type' => 'text', 'label' => 'Name', 'required' => true, 'max' => 100],
            'icon_class' => ['type' => 'icon', 'label' => 'Devicon class', 'help' => 'From devicon.dev, e.g. <code>devicon-react-original colored</code>. Add <code>colored</code> for brand colors.'],
            'icon_image' => ['type' => 'image', 'label' => 'Or upload an icon image', 'help' => 'Used instead of the Devicon class when set. Square PNG works best.'],
        ],
    ],
    'services' => [
        'label' => 'Services', 'singular' => 'Service', 'icon' => 'fa-solid fa-gears',
        'columns' => ['icon' => 'Icon', 'title' => 'Title', 'description' => 'Description'],
        'fields' => [
            'title'       => ['type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 150],
            'description' => ['type' => 'textarea', 'label' => 'Description', 'max' => 400],
            'icon'        => ['type' => 'icon', 'label' => 'Font Awesome icon', 'help' => 'From fontawesome.com/icons (free), e.g. <code>fa-solid fa-code</code>'],
            'link'        => ['type' => 'url', 'label' => '"Learn more" link', 'help' => 'Leave empty to link to the contact form.'],
        ],
    ],
    'packages' => [
        'label' => 'Packages & Prices', 'singular' => 'Package', 'icon' => 'fa-solid fa-tags',
        'columns' => ['icon' => 'Icon', 'name' => 'Package', 'price' => 'Price', 'service' => 'Service', 'is_popular' => 'Popular'],
        'fields' => [
            'name'        => ['type' => 'text', 'label' => 'Package name', 'required' => true, 'max' => 150, 'help' => 'e.g. Portrait Session, Event Coverage, Drone Aerial Package'],
            'service'     => ['type' => 'text', 'label' => 'Service it belongs to', 'max' => 150, 'help' => 'Type the exact name of one of your Services (e.g. Photography) so the "Book" button pre-selects it in the contact form.'],
            'price'       => ['type' => 'text', 'label' => 'Price', 'half' => true, 'max' => 60, 'help' => 'e.g. ₱2,500 or "Starts at ₱8,000" or "Custom quote"'],
            'price_note'  => ['type' => 'text', 'label' => 'Price note', 'half' => true, 'max' => 100, 'help' => 'e.g. per session, per event, per hour'],
            'description' => ['type' => 'textarea', 'label' => 'Short description', 'rows' => 2, 'max' => 400],
            'features'    => ['type' => 'textarea', 'label' => "What's included", 'rows' => 5, 'max' => 2000, 'help' => 'One per line, e.g. "1 hour shoot", "20 edited photos", "Online gallery".'],
            'icon'        => ['type' => 'icon', 'label' => 'Icon', 'help' => 'e.g. <code>fa-solid fa-camera</code>, <code>fa-solid fa-video</code>, <code>icon-drone</code>, <code>fa-solid fa-code</code>'],
            'is_popular'  => ['type' => 'checkbox', 'label' => 'Highlight as "Most Popular"'],
        ],
    ],
    'comparisons' => [
        'label' => 'Before & After', 'singular' => 'Before/after pair', 'icon' => 'fa-solid fa-sliders',
        'columns' => ['after_image' => 'After', 'title' => 'Title', 'caption' => 'Caption'],
        'fields' => [
            'title'        => ['type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 150, 'help' => 'e.g. "Golden hour color grade"'],
            'before_image' => ['type' => 'image', 'label' => 'BEFORE (raw / unedited)', 'half' => true],
            'after_image'  => ['type' => 'image', 'label' => 'AFTER (edited)', 'half' => true],
            'caption'      => ['type' => 'text', 'label' => 'Caption (optional)', 'max' => 300, 'help' => 'What you did, e.g. "Lightroom: exposure, skin tones, warm grade"'],
        ],
    ],
    'experiences' => [
        'label' => 'Experience', 'singular' => 'Experience', 'icon' => 'fa-solid fa-timeline',
        'columns' => ['logo' => 'Logo', 'role' => 'Role', 'company' => 'Company', 'period' => 'Period'],
        'fields' => [
            'role'        => ['type' => 'text', 'label' => 'Role / position', 'required' => true, 'max' => 150],
            'company'     => ['type' => 'text', 'label' => 'Company / organization', 'max' => 150],
            'period'      => ['type' => 'text', 'label' => 'Period', 'help' => 'e.g. 2022 - Present', 'max' => 100],
            'description' => ['type' => 'textarea', 'label' => 'Description', 'max' => 500],
            'logo'        => ['type' => 'image', 'label' => 'Company logo'],
            'icon'        => ['type' => 'icon', 'label' => 'Fallback icon (if no logo)', 'help' => 'Font Awesome class, e.g. <code>fa-solid fa-building</code>'],
        ],
    ],
    'testimonials' => [
        'label' => 'Testimonials', 'singular' => 'Testimonial', 'icon' => 'fa-solid fa-quote-left',
        'columns' => ['avatar' => 'Photo', 'name' => 'Name', 'position' => 'Position', 'rating' => 'Rating'],
        'fields' => [
            'name'     => ['type' => 'text', 'label' => 'Name', 'required' => true, 'max' => 150],
            'position' => ['type' => 'text', 'label' => 'Position & company', 'help' => 'e.g. CTO, Acme Inc.', 'max' => 150],
            'quote'    => ['type' => 'textarea', 'label' => 'Testimonial', 'required' => true, 'max' => 800],
            'avatar'   => ['type' => 'image', 'label' => 'Photo'],
            'rating'   => ['type' => 'number', 'label' => 'Rating (1-5)', 'min' => 1, 'max' => 5, 'default' => 5],
        ],
    ],
    'certificates' => [
        'label' => 'Certificates & Awards', 'singular' => 'Certificate', 'icon' => 'fa-solid fa-award',
        'columns' => ['image' => 'Image', 'title' => 'Title', 'issuer' => 'Issuer', 'issued' => 'Date'],
        'fields' => [
            'title'  => ['type' => 'text', 'label' => 'Certificate / award', 'required' => true, 'max' => 200, 'help' => 'e.g. "Drone Pilot Training", "Best in Photography - Campus Press Conference"'],
            'issuer' => ['type' => 'text', 'label' => 'Issued by', 'half' => true, 'max' => 150],
            'issued' => ['type' => 'text', 'label' => 'Date', 'half' => true, 'max' => 60, 'help' => 'e.g. March 2025'],
            'image'  => ['type' => 'image', 'label' => 'Photo / scan of the certificate (optional)'],
            'link'   => ['type' => 'url', 'label' => 'Verification or post link (optional)'],
        ],
    ],
    'gear' => [
        'label' => 'Gear', 'singular' => 'Gear item', 'icon' => 'fa-solid fa-camera-retro',
        'columns' => ['image' => 'Photo', 'name' => 'Name', 'category' => 'Type', 'note' => 'Note'],
        'fields' => [
            'name'     => ['type' => 'text', 'label' => 'Name', 'required' => true, 'max' => 150, 'help' => 'e.g. "Sony a6400", "DJI Mini 3", "Rode Wireless GO II"'],
            'category' => ['type' => 'select', 'label' => 'Type', 'half' => true, 'options' => GEAR_CATEGORIES],
            'note'     => ['type' => 'text', 'label' => 'Short note (optional)', 'half' => true, 'max' => 200, 'help' => 'e.g. "Main camera", "4K aerials"'],
            'image'    => ['type' => 'image', 'label' => 'Photo (optional)', 'help' => 'Leave empty to show an icon.'],
        ],
    ],
    'posts' => [
        'label' => 'Stories (Blog)', 'singular' => 'Story', 'icon' => 'fa-solid fa-feather-pointed',
        'columns' => ['cover' => 'Cover', 'title' => 'Title', 'published_on' => 'Date', 'slug' => 'Link'],
        'fields' => [
            'title'        => ['type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 200, 'help' => 'e.g. "Behind the shoot: Vivian & Jun Romer\'s wedding"'],
            'published_on' => ['type' => 'date', 'label' => 'Date', 'half' => true],
            'slug'         => ['type' => 'text', 'label' => 'Link name (optional)', 'half' => true, 'max' => 120, 'help' => 'Made from the title if empty, e.g. <code>behind-the-wedding-shoot</code>'],
            'cover'        => ['type' => 'image', 'label' => 'Cover photo'],
            'excerpt'      => ['type' => 'textarea', 'label' => 'Short summary (shown on the card)', 'rows' => 2, 'max' => 400],
            'body'         => ['type' => 'textarea', 'label' => 'Story', 'rows' => 16, 'max' => 60000,
                'help' => 'Write normally; leave an empty line between paragraphs. Extras: <code>## Heading</code>, <code>- list item</code>, <code>&gt; quote</code>, <code>**gold text**</code>, <code>[link text](https://...)</code>, and a photo on its own line: <code>![caption](https://... or uploads/...)</code>.'],
        ],
    ],
    'socials' => [
        'label' => 'Social Links', 'singular' => 'Social link', 'icon' => 'fa-solid fa-share-nodes',
        'columns' => ['icon' => 'Icon', 'platform' => 'Platform', 'url' => 'URL'],
        'fields' => [
            'platform' => ['type' => 'text', 'label' => 'Platform name', 'required' => true, 'max' => 80],
            'icon'     => ['type' => 'icon', 'label' => 'Font Awesome icon', 'help' => 'e.g. <code>fa-brands fa-github</code>, <code>fa-brands fa-facebook-f</code>, <code>fa-brands fa-tiktok</code>'],
            'url'      => ['type' => 'url', 'label' => 'Profile URL', 'required' => true],
        ],
    ],
];
