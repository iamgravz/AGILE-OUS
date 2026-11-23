<?php /** @var \App\Core\View $this */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<?php $pageName = $this->section('title'); ?>
<title><?= $pageName === '' ? e(config('app.name')) : $pageName . ' | ' . e(config('app.name')) ?></title>
<meta name="description" content="<?= $this->section('description', 'AGILE is the BSIT student organization of the PUP Open University System: membership, events and student welfare services.') ?>">
<link rel="icon" href="<?= e(asset('img/logo.png')) ?>" type="image/png">
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
<?php /* One look for every page: the bands, cards and buttons of the public redesign (Nov 23, 2026). */ ?>
<link rel="stylesheet" href="<?= e(asset('css/modern.css')) ?>">
<?= $this->section('head') ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
