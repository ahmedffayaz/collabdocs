<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ajaia Docs — a quieter place to write</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app" data-routes="{{ json_encode([
        'documents' => route('api.documents.index'),
        'activeUser' => route('api.active-user'),
        'createDocument' => route('api.documents.store'),
        'importDocument' => route('api.documents.import'),
        'document' => route('api.documents.show', ['document' => '__DOCUMENT__']),
        'updateDocument' => route('api.documents.update', ['document' => '__DOCUMENT__']),
        'deleteDocument' => route('api.documents.destroy', ['document' => '__DOCUMENT__']),
        'shareDocument' => route('api.documents.share', ['document' => '__DOCUMENT__']),
    ]) }}"></div>
</body>
</html>
