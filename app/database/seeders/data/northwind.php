<?php

/*
 * Curated demo data for Northwind Studio, a fictional Jakarta digital agency.
 * Everything here is synthetic. Phone numbers use reserved or clearly fictional ranges.
 */

return [
    'accounts' => [
        // name, domain, industry, size, health
        'sukha' => ['Sukha Living', 'sukhaliving.id', 'E-commerce', '11-50', 'good'],
        'tuku' => ['Kopi Tuku Nusantara', 'kopituku.id', 'F&B', '51-200', 'good'],
        'halden' => ['Halden Dental (UK)', 'haldendental.co.uk', 'Healthcare', '11-50', 'fair'],
        'batik' => ['Batik Keraton', 'batikkeraton.id', 'Retail', '1-10', 'fair'],
        'finwise' => ['Finwise Pay', 'finwise.io', 'Fintech', '51-200', 'poor'],
        'telusur' => ['Telusur Travel', 'telusur.id', 'Travel', '1-10', 'good'],
        'marlowe' => ['Marlowe & Finch (SG)', 'marlowefinch.sg', 'Professional services', '11-50', 'good'],
        'senyum' => ['Klinik Gigi Senyum Sehat', 'senyumsehat.id', 'Healthcare', '11-50', 'good'],
        'sari' => ['Sari Rasa Catering', 'sarirasa.id', 'F&B', '1-10', 'fair'],
        'halim' => ['Halim & Partners Law', 'halimlaw.id', 'Professional services', '11-50', 'good'],
    ],

    // key => name, phone, email, account, source, tags, owner, score reasons (optional)
    'contacts' => [
        'dewi' => ['Dewi Anggraini', '+6281244710932', 'dewi@sukhaliving.id', 'sukha', 'whatsapp', ['hot'], 'bimo'],
        'tom' => ['Tom Halden', '+447700900123', 'tom@haldendental.co.uk', 'halden', 'referral', ['b2b'], 'citra'],
        'arif' => ['Arif Rahman', '+6281322005517', 'arif@kopituku.id', 'tuku', 'whatsapp', ['retainer'], 'dimas'],
        'joko' => ['Joko Widarto', '+6281286630045', null, 'batik', 'web', ['follow-up'], 'elena'],
        'rina' => ['Rina Kusuma', '+6285215550198', 'rina@telusur.id', 'telusur', 'whatsapp', [], 'bimo'],
        'meilin' => ['Mei Lin Tan', '+6591110247', 'meilin@marlowefinch.sg', 'marlowe', 'referral', ['b2b'], 'citra'],
        'bayu' => ['Bayu Firmansyah', '+6281990013342', 'bayu@finwise.io', 'finwise', 'web', ['ecommerce'], 'dimas'],
        'putri' => ['Putri Amelia', '+6281774408821', 'putri@senyumsehat.id', 'senyum', 'whatsapp', ['hot'], 'elena'],
        'lina' => ['Lina Marlina', '+6281511820764', null, 'sari', 'whatsapp', [], 'bimo'],
        'agus' => ['Agus Salim', '+6281233904456', 'agus.salim@halimlaw.id', 'halim', 'referral', ['b2b'], 'citra'],
        'yoga' => ['Yoga Saputra', '+6281399260718', null, null, 'whatsapp', ['follow-up'], 'dimas'],
        'nadia' => ['Nadia Zahra', '+6285718830621', 'nadia.zahra@gmail.com', null, 'web', [], 'elena'],
        'tono' => ['Tono Wibowo', '+6281214477703', null, 'batik', 'whatsapp', [], 'bimo'],
        'sarah' => ['Sarah Chen', '+6598880142', 'sarah@marlowefinch.sg', 'marlowe', 'referral', [], 'citra'],
        'fajar' => ['Fajar Hidayat', '+6281380054429', 'fajar@sarirasa.id', 'sari', 'whatsapp', ['retainer'], 'dimas'],
        'intan' => ['Intan Permata', '+6281910047750', 'intan@senyumsehat.id', 'senyum', 'web', [], 'elena'],
        'hendra' => ['Hendra Gunawan', '+6281266691153', 'hendra@kopituku.id', 'tuku', 'manual', ['b2b'], 'dimas'],
        'ratna' => ['Ratna Sari', '+6285290037716', 'ratna@sukhaliving.id', 'sukha', 'whatsapp', [], 'bimo'],
        'james' => ['James Whitfield', '+447700900456', 'james@haldendental.co.uk', 'halden', 'referral', [], 'citra'],
        'kenji' => ['Kenji Pratama', '+6281316630922', null, null, 'whatsapp', [], 'elena'],
    ],

    // Conversation scripts: [direction, text, minutes after the previous message]
    'scripts' => [
        'shopify' => [
            ['in', 'Halo, kami mau migrasi toko dari WooCommerce ke Shopify. Sekitar 340 produk, ada varian ukuran dan warna.', 0],
            ['out', 'Halo Kak Dewi, bisa. Kami sudah beberapa kali migrasi dari WooCommerce. Boleh tahu target live-nya kapan?', 3],
            ['in', 'Sebelum Harbolnas 12.12. Budget kami sekitar 60 juta, sudah termasuk desain ulang halaman produk.', 8],
            ['out', 'Noted, Kak. Kami kirim estimasi timeline dan penawaran sore ini ya.', 4],
            ['in', 'Kalau mulai minggu depan, masih sempat selesai sebelum 12.12?', 40],
        ],
        'dental' => [
            ['in', "Hi, found you through Kopi Tuku. We're a dental group in Manchester and need a new website with online booking.", 0],
            ['out', 'Hi Tom, thanks for getting in touch. How many clinics, and which booking system do you use today?', 11],
            ['in', 'Three clinics. We use Dentally at the moment but are open to switching if the integration is painful.', 95],
            ['out', "Good to know. I'll put together two options with a rough budget and send them tomorrow.", 20],
            ['in', "Great. We'd like to decide before the end of the month, and the budget is around £8,000.", 180],
        ],
        'seo' => [
            ['in', 'Kak, tahun ini kami mau fokus ke SEO untuk 12 outlet baru. Bisa bantu?', 0],
            ['out', 'Bisa, Kak Arif. Apakah tiap outlet punya halaman sendiri atau cuma di Google Business Profile?', 14],
            ['in', 'Sekarang baru Google Business Profile. Website cuma satu.', 25],
            ['out', 'Oke. Kami usulkan struktur halaman lokasi plus program 6 bulan. Penawaran kami kirim lusa.', 9],
        ],
        'price' => [
            ['in', 'Terima kasih penawarannya. Jujur agak di atas budget kami, terutama bagian iklan berbayar.', 0],
            ['out', 'Terima kasih masukannya, Kak. Kami bisa pecah jadi dua tahap supaya biaya awal lebih ringan.', 22],
            ['in', 'Boleh, tolong kirim versi dua tahapnya. Kami diskusi dulu dengan tim keuangan.', 130],
            ['out', 'Siap, Kak. Versi dua tahap kami kirim ke email hari ini.', 40],
        ],
        'silent' => [
            ['in', 'Halo, kami butuh refresh brand untuk 2027. Ada contoh pekerjaan sebelumnya?', 0],
            ['out', 'Halo Kak Joko, ini beberapa studi kasus kami. Kalau berkenan, kita bisa jadwalkan 30 menit untuk bahas kebutuhan.', 16],
            ['out', 'Kak Joko, apakah ada kabar? Kami kirim ulang jadwal yang kosong minggu ini ya.', 1800],
        ],
        'app' => [
            ['in', 'Kami lagi menjajaki aplikasi booking tiket untuk tur lokal. Apakah Northwind menangani aplikasi mobile?', 0],
            ['out', 'Menangani, Kak Rina. Kami biasanya mulai dengan sesi discovery 1 minggu untuk memetakan fitur dan estimasi.', 31],
            ['in', 'Menarik. Kalau discovery-nya berapa biayanya?', 260],
        ],
        'examples' => [
            ['in', 'Hi, can you share examples of similar work? We run a legal-tech blog and want a faster site.', 0],
            ['out', 'Of course. Sending three recent projects now, including one for a law firm.', 18],
            ['in', 'Looks good. What are the next steps?', 70],
            ['out', 'Next step is a 30-minute call this week, then a fixed-price proposal. Does Thursday work?', 35],
        ],
        'hosting' => [
            ['in', 'Kak, kalau website sudah jadi, hosting-nya sekalian dari Northwind atau bagaimana?', 0],
            ['out', 'Bisa dua-duanya, Kak. Kami sarankan hosting terkelola supaya update dan backup kami yang urus.', 12],
            ['in', 'Oke, tolong sertakan di penawaran ya. Estimasi bulanannya berapa?', 55],
            ['out', 'Sekitar Rp 450 ribu per bulan untuk hosting terkelola. Kami masukkan ke penawaran.', 25],
        ],
        'meeting' => [
            ['out', 'Halo Kak Putri, kami konfirmasi meeting Kamis jam 10.00 untuk bahas website klinik ya.', 0],
            ['in', 'Baik Kak. Bisa dimajukan ke jam 09.30? Setelah itu ada pasien.', 52],
            ['out', 'Bisa. Kami atur jadi 09.30. Link meeting kami kirim besok pagi.', 7],
            ['in', 'Siap, terima kasih.', 3],
            ['out', 'Sama-sama, Kak. Sampai jumpa hari Kamis!', 10],
        ],
        'invoice' => [
            ['out', 'Kak Mei Lin, invoice proyek bulan lalu sudah kami kirim ke email ya.', 0],
            ['in', 'Thanks, invoice received. Payment goes out on Friday.', 140],
            ['out', 'Terima kasih. Kami tunggu, dan kabari kalau ada pertanyaan.', 6],
        ],
        'catering' => [
            ['in', 'Halo Northwind, kami catering rumahan, mau bikin website pesan online. Bisa dengan budget kecil?', 0],
            ['out', 'Bisa, Kak Lina. Untuk budget kecil kami sarankan landing page dengan form pesanan dulu.', 25],
            ['in', 'Boleh. Kira-kira kisaran harganya?', 600],
        ],
        'agency' => [
            ['in', 'Pak, kami tertarik retainer konten bulanan. Ada paket mulai dari berapa?', 0],
            ['in', 'Sekalian kalau bisa include jadwal posting.', 2],
        ],
        'law' => [
            ['in', 'Selamat siang. Kantor kami butuh website baru dan blog artikel hukum. Siapa yang bisa kami hubungi?', 0],
            ['out', 'Siang Pak Agus. Saya Citra dari Northwind. Boleh kami telepon besok untuk memahami kebutuhan?', 28],
            ['in', 'Boleh, setelah jam 2 siang ya.', 120],
            ['out', 'Siap. Kami telepon pukul 14.30.', 5],
        ],
        'failed' => [
            ['in', 'Halo, soal penawaran kemarin, boleh kami minta revisi di bagian timeline?', 0],
            ['out', 'Boleh, Kak. Kami revisi dan kirim sore ini.', 20],
            ['out', 'Revisi penawaran sudah kami kirim ke email Kak Bayu. Mohon dicek ya.', 1700, 'failed'],
        ],
        'old' => [
            ['in', 'Terima kasih, kami putuskan pakai tim internal dulu untuk saat ini.', 0],
            ['out', 'Baik Kak Nadia, terima kasih sudah mempertimbangkan kami. Kabari kalau butuh bantuan di kemudian hari.', 35],
        ],
        'quick' => [
            ['in', 'Halo kak, harga untuk iklan Meta per bulan berapa ya?', 0],
        ],
        'intl' => [
            ['in', "Hello, we're a Singapore consultancy looking for a design partner in Jakarta for a recurring engagement.", 0],
            ['out', 'Hello Sarah, thanks for reaching out. We have worked with SG teams before. Could we schedule an intro call?', 25],
            ['in', 'Yes. Tuesday afternoon SGT works for us.', 220],
            ['out', 'Tuesday 14:00 SGT works. I will send a calendar invite shortly.', 45],
        ],
    ],

    // Conversations: contact key, script, minutes since the last message, status, assignee (default owner)
    'conversations' => [
        ['dewi', 'shopify', 38, 'open', null],
        ['tom', 'dental', 95, 'open', null],
        ['arif', 'seo', 300, 'open', null],
        ['bayu', 'failed', 90, 'open', null],
        ['putri', 'meeting', 410, 'open', null],
        ['meilin', 'invoice', 1500, 'closed', null],
        ['joko', 'silent', 1650, 'open', null],
        ['rina', 'app', 55, 'open', null],
        ['agus', 'law', 2900, 'open', null],
        ['sarah', 'intl', 130, 'open', null],
        ['lina', 'catering', 960, 'open', null],
        ['hendra', 'price', 190, 'open', null],
        ['james', 'examples', 210, 'open', null],
        ['fajar', 'hosting', 1210, 'open', null],
        ['yoga', 'agency', 18, 'open', null],
        ['nadia', 'old', 6200, 'closed', null],
        ['kenji', 'quick', 25, 'open', null],
    ],

    'templates' => [
        ['followup_no_response', 'Hi {1}, just following up on your inquiry. Is there anything else you need from us?', 'UTILITY', 'en_US'],
        ['follow_up_penawaran', 'Halo {1}, kami follow up penawaran yang sudah dikirim. Ada yang ingin didiskusikan?', 'UTILITY', 'id'],
        ['quote_ready', 'Hi {1}, your quote is ready. Reply here and we will walk you through it.', 'UTILITY', 'en_US'],
        ['welcome_new_lead', 'Hi {1}, thanks for reaching out to Northwind Studio! How can we help?', 'MARKETING', 'en_US'],
    ],

    // title, account, contact, stage, value, currency, close in days, owner, days in stage, extra
    'deals' => [
        ['Shopify migration', 'sukha', 'dewi', 'Proposal', 60000000, 'IDR', 12, 'bimo', 3],
        ['Website and booking rebuild', 'halden', 'tom', 'Qualified', 8000, 'GBP', 28, 'citra', 2],
        ['SEO 12 outlets, 6 months', 'tuku', 'arif', 'Proposal', 96000000, 'IDR', 20, 'dimas', 2],
        ['Performance ads retainer', 'tuku', 'hendra', 'Negotiation', 72000000, 'IDR', 9, 'dimas', 2],
        ['Brand refresh 2027', 'batik', 'joko', 'Qualified', 45000000, 'IDR', 40, 'elena', 13],
        ['Booking app discovery', 'telusur', 'rina', 'New', 35000000, 'IDR', 45, 'bimo', 1],
        ['Clinic website refresh', 'senyum', 'putri', 'Proposal', 52000000, 'IDR', 14, 'elena', 5],
        ['Design partner retainer', 'marlowe', 'sarah', 'Qualified', 6500, 'SGD', 35, 'citra', 4],
        ['Content engine setup', 'sari', 'fajar', 'New', 18000000, 'IDR', 30, 'dimas', 9],
        ['Catering order landing page', 'sari', 'lina', 'New', 12000000, 'IDR', 25, 'bimo', 2],
        ['Law firm site and blog', 'halim', 'agus', 'Proposal', 68000000, 'IDR', 18, 'citra', 9],
        ['Legal-tech blog speed-up', 'halim', 'james', 'New', null, 'IDR', 50, 'citra', 3],
        ['Conversion audit', 'finwise', 'bayu', 'Negotiation', 28000000, 'IDR', 7, 'dimas', 7],
        ['Social retainer Q1', 'batik', 'tono', 'Qualified', 24000000, 'IDR', 33, 'bimo', 6],
        ['Email automation pilot', 'sukha', 'ratna', 'Qualified', 31000000, 'IDR', 26, 'bimo', 12],
        ['Meta ads, always-on', null, 'kenji', 'New', null, 'IDR', 60, 'elena', 1],
        ['Website redesign retainer', 'senyum', 'intan', 'New', 22000000, 'IDR', 38, 'elena', 8],
        ['Headless commerce build', 'sukha', 'ratna', 'Negotiation', 165000000, 'IDR', 15, 'bimo', 3],
        // closed this quarter
        ['Content retainer, 6 months', 'tuku', 'arif', 'Won', 54000000, 'IDR', null, 'dimas', 12, 'won'],
        ['Brand identity', 'marlowe', 'meilin', 'Won', 7200, 'SGD', null, 'citra', 20, 'won'],
        ['Campaign landing pages', 'sukha', 'dewi', 'Won', 38000000, 'IDR', null, 'bimo', 9, 'won'],
        ['Clinic booking integration', 'halden', 'james', 'Won', 5400, 'GBP', null, 'citra', 26, 'won'],
        ['Spring campaign pages', 'sukha', 'dewi', 'Won', 33000000, 'IDR', null, 'bimo', 108, 'won'],
        ['SEO audit and fixes', 'tuku', 'arif', 'Won', 24000000, 'IDR', null, 'bimo', 117, 'won'],
        ['Product photography site', 'batik', 'tono', 'Lost', 29000000, 'IDR', null, 'elena', 15, 'Went with in-house'],
        ['Catering platform', 'sari', 'fajar', 'Lost', 41000000, 'IDR', null, 'dimas', 22, 'Price too high'],
        ['Fintech onboarding flow', 'finwise', 'bayu', 'Lost', 88000000, 'IDR', null, 'dimas', 31, 'No budget this quarter'],
        ['Travel blog rebuild', 'telusur', 'rina', 'Lost', 19000000, 'IDR', null, 'bimo', 18, 'No budget this quarter'],
    ],

    'notes' => [
        'Shopify migration' => ['Dewi wants a 4-week plan. Product export from WooCommerce is clean; variants need a mapping sheet. @ayu can you confirm we have capacity from 9 Nov?'],
        'Booking app discovery' => ['Discovery call booked. Telusur currently sells tours via WhatsApp only.'],
        'Performance ads retainer' => ['Kopi Tuku approved the first two months in principle; finance wants a single monthly invoice.'],
        'Brand refresh 2027' => ['Joko has not replied since the second follow-up. Try a different channel (email).'],
        'Law firm site and blog' => ['Agus prefers calls after 14:00. Needs bilingual (ID/EN) pages.'],
        'Headless commerce build' => ['Largest open deal. Sukha wants fixed scope and a phased rollout.'],
    ],
];
