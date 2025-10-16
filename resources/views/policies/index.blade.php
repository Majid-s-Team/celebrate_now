<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: "Segoe UI", Tahoma, sans-serif;
            margin: 40px auto;
            max-width: 800px;
            line-height: 1.6;
            color: #333;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .content {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        a {
            color: #3498db;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <h1>{{ $title }}</h1>

    <div class="content">
        {!! $policy->content !!}
    </div>

</body>
</html>
