@extends('layouts.elite-mobile-marketplace')
@section('title','Installment Service | خدمة التقسيط')
@section('content')
<x-public.public-header />
<main class="mx-auto max-w-5xl space-y-6 px-4 py-8" dir="auto">
 <section class="pm-hero-card text-white"><p class="text-sm font-bold text-cyan-200">Installment Service / خدمة التقسيط</p><h1 class="mt-3 text-3xl font-extrabold">Your next device, paid over time.<br><span lang="ar" dir="rtl">جهازك الجديد بالتقسيط</span></h1><p class="mt-4 max-w-2xl">Apply online, or visit our store. Approval is subject to review.</p><a class="pm-button pm-button--secondary mt-6" href="{{ route('installments.create') }}">Apply now / قدّم الآن</a></section>
 <section class="grid gap-4 md:grid-cols-3"><article class="pm-card"><h2 class="font-bold">1. Apply / قدّم الطلب</h2><p class="mt-2 text-sm text-slate-600">Choose your device and upload the required documents.</p></article><article class="pm-card"><h2 class="font-bold">2. Review / مراجعة</h2><p class="mt-2 text-sm text-slate-600">We review your request; approval is not guaranteed.</p></article><article class="pm-card"><h2 class="font-bold">3. Collect / استلم الجهاز</h2><p class="mt-2 text-sm text-slate-600">After approval, visit a store, sign the agreement, and pay the first installment.</p></article></section>
 <section class="pm-card"><h2 class="text-xl font-bold">Required documents / المستندات المطلوبة</h2><ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700"><li>Lebanese ID, Lebanese passport, or Civil Registry Extract (إخراج قيد).</li><li>Proof of address: residence certificate, electricity bill, or water bill.</li><li>The address document must be in your name or one of your parents’ names.</li><li>Remaining approved installments are paid through Whish Money.</li></ul></section>
</main>
@endsection
