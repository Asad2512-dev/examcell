@props(['theme'=>true])
@if($theme)
<script>(function(){try{const t=localStorage.getItem('examcell-theme');document.documentElement.classList.toggle('dark',t==='dark'||(!t&&matchMedia('(prefers-color-scheme: dark)').matches))}catch(e){}})()</script>
@endif
<script>window.examcellContent = {{ \Illuminate\Support\Js::from(ui_frontend_content()) }};</script>
@vite(['resources/css/app.css','resources/js/app.js'])
<x-brand-head />
<link rel="stylesheet" href="{{ route('ui.palette') }}">
