<?php

/*
 * Plain-data legal/policy pages, rendered by Marketing/Legal.vue at /privacy,
 * /terms and /guidelines. Edit the copy here — no code changes needed.
 *
 * These are sensible, general-purpose templates for a community platform. They
 * are NOT legal advice; review them (and the contact address below) before
 * relying on them.
 */

$contact = 'support@convoro.co';
$updated = 'June 20, 2026';

return [

    'privacy' => [
        'title' => 'Privacy Policy',
        'updated' => $updated,
        'intro' => 'This Privacy Policy explains what information Convoro (“we”, “us”) collects when you use this community, how we use it, and the choices you have. By using the community you agree to the practices described here.',
        'sections' => [
            ['h' => 'Information we collect', 'body' => [
                'Account information you provide when you register — such as your username, email address, password (stored only as a secure hash) and any optional profile details.',
                'Content you create — posts, comments, messages, reactions, uploads and other material you submit to the community.',
                'Technical and usage data collected automatically — your IP address, browser and device information, pages viewed and approximate location derived from your IP, used to operate and secure the service.',
            ]],
            ['h' => 'How we use information', 'list' => [
                'To provide, maintain and improve the community and its features.',
                'To authenticate you, keep your account secure and prevent abuse, spam and fraud.',
                'To send you service messages and the notifications you have opted into (you can change these in your settings).',
                'To comply with our legal obligations and enforce our Terms of Use and Community Guidelines.',
            ]],
            ['h' => 'Cookies', 'body' => [
                'We use strictly necessary cookies to keep you signed in and to remember preferences such as your theme. We do not use third-party advertising cookies. You can clear or block cookies in your browser, though some features may stop working.',
            ]],
            ['h' => 'How information is shared', 'body' => [
                'We do not sell your personal information. Content you post publicly is visible to others by design. We share limited data only with service providers who help us run the community (such as hosting and email delivery), and only as needed to provide the service, or where required by law or to protect rights and safety.',
            ]],
            ['h' => 'Data retention', 'body' => [
                'We keep your information for as long as your account is active or as needed to provide the service. You may delete your content or request account deletion at any time; some records may be retained where required for legal, security or backup purposes.',
            ]],
            ['h' => 'Your rights', 'body' => [
                'Depending on where you live, you may have the right to access, correct, export or delete your personal information, and to object to or restrict certain processing. You can manage most of this from your account settings, or contact us using the details below.',
            ]],
            ['h' => 'Children', 'body' => [
                'The community is not directed to children under 13 (or the minimum age required in your country), and we do not knowingly collect their personal information. If you believe a child has provided us information, contact us and we will remove it.',
            ]],
            ['h' => 'Security', 'body' => [
                'We use reasonable technical and organisational measures to protect your information, including encryption in transit and hashed passwords. No system is perfectly secure, so we cannot guarantee absolute security.',
            ]],
            ['h' => 'Changes to this policy', 'body' => [
                'We may update this policy from time to time. We will post the updated version here and revise the “last updated” date; significant changes will be communicated within the community.',
            ]],
            ['h' => 'Contact us', 'body' => [
                "Questions about privacy? Email us at {$contact}.",
            ]],
        ],
    ],

    'terms' => [
        'title' => 'Terms of Use',
        'updated' => $updated,
        'intro' => 'These Terms of Use govern your access to and use of this community. By creating an account or otherwise using the community, you agree to these Terms and to our Community Guidelines and Privacy Policy.',
        'sections' => [
            ['h' => 'Your account', 'body' => [
                'You must provide accurate information, keep your password secure, and are responsible for activity that happens under your account. You must be old enough to use the community in your jurisdiction. Notify us promptly of any unauthorised use.',
            ]],
            ['h' => 'Your content', 'body' => [
                'You retain ownership of the content you post. By posting, you grant us a non-exclusive, worldwide, royalty-free licence to host, store, display and distribute that content for the purpose of operating and promoting the community. You are responsible for what you post and confirm you have the rights to share it.',
            ]],
            ['h' => 'Acceptable use', 'body' => [
                'You agree to follow our Community Guidelines. In particular, you must not:',
            ], 'list' => [
                'Post unlawful, infringing, harassing, hateful or sexually exploitative content.',
                'Spam, scam, phish or distribute malware.',
                'Attempt to disrupt, overload, reverse-engineer or gain unauthorised access to the service.',
                'Harvest other members’ data or impersonate others.',
            ]],
            ['h' => 'Moderation & termination', 'body' => [
                'We may remove content and suspend or terminate accounts that violate these Terms or our Community Guidelines, at our discretion. You may stop using the community and delete your account at any time.',
            ]],
            ['h' => 'Intellectual property', 'body' => [
                'The community software, branding and design are owned by us or our licensors and are protected by law. Except for your own content, nothing here grants you rights to our intellectual property.',
            ]],
            ['h' => 'Disclaimers', 'body' => [
                'The community is provided “as is” and “as available”, without warranties of any kind, to the fullest extent permitted by law. We do not endorse and are not responsible for content posted by members.',
            ]],
            ['h' => 'Limitation of liability', 'body' => [
                'To the fullest extent permitted by law, we will not be liable for any indirect, incidental, special or consequential damages, or for loss of data, profits or goodwill, arising from your use of the community.',
            ]],
            ['h' => 'Changes to these terms', 'body' => [
                'We may update these Terms from time to time. Continued use of the community after changes take effect means you accept the revised Terms.',
            ]],
            ['h' => 'Contact us', 'body' => [
                "Questions about these Terms? Email us at {$contact}.",
            ]],
        ],
    ],

    'guidelines' => [
        'title' => 'Community Guidelines',
        'updated' => $updated,
        'intro' => 'These guidelines keep the community welcoming, useful and safe. They apply to every post, comment, message, upload and profile. Breaking them may result in content removal, warnings, or loss of access.',
        'sections' => [
            ['h' => 'Be respectful', 'body' => [
                'Treat others as you would want to be treated. Disagree with ideas, not people. Personal attacks, harassment, hate speech and threats are not allowed.',
            ]],
            ['h' => 'Keep it safe', 'list' => [
                'No content that is illegal, sexually exploitative, or harmful to minors.',
                'No doxxing or sharing anyone’s private information without consent.',
                'No promotion of self-harm, violence or dangerous activities.',
            ]],
            ['h' => 'No spam or scams', 'body' => [
                'Don’t flood the community with repetitive posts, undisclosed advertising, referral schemes, phishing or malware. Self-promotion is welcome only where it’s relevant and allowed.',
            ]],
            ['h' => 'Post in the right place', 'body' => [
                'Use a clear title, search before posting duplicates, and pick the category that fits. Keep discussions on topic so others can find and follow them.',
            ]],
            ['h' => 'Respect ownership', 'body' => [
                'Only post content you created or have permission to share, and credit sources where appropriate. Don’t infringe copyrights, trademarks or other rights.',
            ]],
            ['h' => 'Reporting & enforcement', 'body' => [
                'If you see something that breaks these guidelines, report it to the moderators. Staff may edit or remove content, and warn, suspend or ban accounts, to keep the community healthy. Decisions aim to be fair and consistent.',
            ]],
            ['h' => 'Questions', 'body' => [
                "Not sure about something? Ask a moderator, or email {$contact}.",
            ]],
        ],
    ],

];
