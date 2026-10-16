@extends('layouts.public')

@section('body')
<nav class="navbar navbar-expand-lg navbar-dark public-nav fixed-top py-2">
    <div class="container">
        @include('partials.brand')
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="القائمة"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="#about">عن المركز</a></li>
                <li class="nav-item"><a class="nav-link" href="#programs">البرامج والحلقات</a></li>
                <li class="nav-item"><a class="nav-link" href="#news">الأخبار</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">تواصل معنا</a></li>
            </ul>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-gold"><i class="bi bi-speedometer2 ms-1"></i> لوحة التحكم</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-gold"><i class="bi bi-box-arrow-in-left ms-1"></i> تسجيل الدخول</a>
            @endauth
        </div>
    </div>
</nav>

<header class="hero text-center">
    <div class="container position-relative" style="z-index:1">
        <p class="ayah mb-3">﴿ وَلَقَدْ يَسَّرْنَا الْقُرْآنَ لِلذِّكْرِ فَهَلْ مِن مُّدَّكِرٍ ﴾</p>
        <h1 class="mb-3">{{ \App\Models\Setting::get('center_name') }}</h1>
        <p class="lead mx-auto mb-4" style="max-width:640px">{{ \App\Models\Setting::get('center_about') }}</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="#programs" class="btn btn-gold btn-lg px-4">تعرّف على حلقاتنا</a>
            <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg px-4">دخول أولياء الأمور والمحفظين</a>
        </div>
    </div>
</header>

<section class="py-4 bg-white border-bottom">
    <div class="container">
        <div class="row g-3">
            <div class="col-4 public-stat"><div class="num">{{ $stats['students'] }}</div><div class="text-muted">طالب وطالبة</div></div>
            <div class="col-4 public-stat"><div class="num">{{ $stats['teachers'] }}</div><div class="text-muted">محفظ ومحفظة</div></div>
            <div class="col-4 public-stat"><div class="num">{{ $stats['circles'] }}</div><div class="text-muted">حلقة قرآنية</div></div>
        </div>
    </div>
</section>

<section id="about" class="section">
    <div class="container">
        <h2 class="section-title text-center">عن المركز</h2>
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-body p-4">
                    <h5 class="fw-bold text-success"><i class="bi bi-bullseye ms-2"></i>رسالتنا</h5>
                    <p class="mb-0">{{ \App\Models\Setting::get('center_mission') }}</p>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-body p-4">
                    <h5 class="fw-bold text-success"><i class="bi bi-flag ms-2"></i>أهدافنا</h5>
                    <ul class="mb-0">
                        @foreach (preg_split('/\r?\n/', (string) \App\Models\Setting::get('center_goals')) as $goal)
                            @if (trim($goal) !== '')<li>{{ $goal }}</li>@endif
                        @endforeach
                    </ul>
                </div></div>
            </div>
        </div>
        <div class="row g-4 mt-2 text-center">
            @foreach ([['journal-bookmark', 'متابعة يومية', 'تسجيل الحفظ والمراجعة لكل طالب بدقة'], ['people', 'محفظون مجازون', 'نخبة من المحفظين المتقنين'], ['phone', 'تواصل مع الأسرة', 'لوحة خاصة لولي الأمر لمتابعة ابنه'], ['award', 'تقييم مستمر', 'تقارير شهرية عن مستوى الطالب']] as [$icon, $title, $text])
                <div class="col-6 col-lg-3">
                    <div class="feature-icon mx-auto mb-2"><i class="bi bi-{{ $icon }}"></i></div>
                    <h6 class="fw-bold mb-1">{{ $title }}</h6>
                    <small class="text-muted">{{ $text }}</small>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section id="programs" class="section bg-white">
    <div class="container">
        <h2 class="section-title text-center">البرامج والحلقات المتاحة</h2>
        <div class="row g-4">
            @forelse ($circles as $circle)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="fw-bold mb-0">{{ $circle->name }}</h5>
                                @if ($circle->level)<span class="badge bg-gold-soft text-dark">{{ $circle->level }}</span>@endif
                            </div>
                            <p class="text-muted small">{{ $circle->description }}</p>
                            <ul class="list-unstyled small mb-0">
                                @if ($circle->age_group)<li><i class="bi bi-people ms-1 text-success"></i> الفئة العمرية: {{ $circle->age_group }}</li>@endif
                                <li><i class="bi bi-calendar3 ms-1 text-success"></i> {{ $circle->daysLabel() }}</li>
                                <li><i class="bi bi-clock ms-1 text-success"></i> {{ $circle->timeLabel() }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><x-empty icon="bullseye" message="لا توجد حلقات معروضة حالياً." /></div>
            @endforelse
        </div>
    </div>
</section>

<section id="news" class="section">
    <div class="container">
        <h2 class="section-title text-center">الإعلانات والأخبار</h2>
        <div class="row g-4">
            @forelse ($announcements as $a)
                <div class="col-md-4">
                    <div class="card h-100"><div class="card-body">
                        <small class="text-gold fw-semibold">{{ ($a->publish_at ?? $a->created_at)->translatedFormat('d F Y') }}</small>
                        <h5 class="fw-bold mt-1">{{ $a->title }}</h5>
                        <p class="text-muted mb-0">{{ \Illuminate\Support\Str::limit($a->body, 180) }}</p>
                    </div></div>
                </div>
            @empty
                <div class="col-12"><x-empty icon="megaphone" message="لا توجد إعلانات حالياً." /></div>
            @endforelse
        </div>
    </div>
</section>

<section id="contact" class="section bg-white">
    <div class="container">
        <h2 class="section-title text-center">تواصل معنا</h2>
        <div class="row g-4">
            <div class="col-lg-4">
                <ul class="list-unstyled fs-6">
                    <li class="mb-3"><i class="bi bi-telephone text-success ms-2"></i> <span dir="ltr">{{ \App\Models\Setting::get('contact_phone') }}</span></li>
                    <li class="mb-3"><i class="bi bi-envelope text-success ms-2"></i> {{ \App\Models\Setting::get('contact_email') }}</li>
                    <li class="mb-3"><i class="bi bi-geo-alt text-success ms-2"></i> {{ \App\Models\Setting::get('contact_address') }}</li>
                </ul>
            </div>
            <div class="col-lg-8">
                @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                <form method="POST" action="{{ route('contact.store') }}" class="card card-body" novalidate>
                    @csrf
                    <div class="row g-3">
                        <x-field name="name" label="الاسم" required col="col-md-4" />
                        <x-field name="email" label="البريد الإلكتروني" type="email" col="col-md-4" />
                        <x-field name="phone" label="رقم الجوال" col="col-md-4" dir="ltr" />
                        <x-field name="message" label="رسالتك" type="textarea" required col="col-12" />
                        <div class="col-12"><button class="btn btn-primary px-4"><i class="bi bi-send ms-1"></i> إرسال</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<footer class="site-footer py-4">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
        @include('partials.brand')
        <div class="small">© {{ now()->year }} {{ \App\Models\Setting::get('center_name') }}. جميع الحقوق محفوظة.</div>
        <a href="{{ route('login') }}" class="small"><i class="bi bi-box-arrow-in-left ms-1"></i>تسجيل الدخول</a>
    </div>
</footer>
@endsection
