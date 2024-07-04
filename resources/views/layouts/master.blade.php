<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests"> --}}
    <title>Masbro &mdash; Canteen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"
        integrity="sha512-HK5fgLBL+xu6dm/Ii3z4xhlSUyZgTT9tuc/hSrtw6uzJOvgRr2a9jyxxT1ely+B+xFAmJKVSTbpM/CuL7qxO8w=="
        crossorigin="anonymous" />

    <link rel="stylesheet" href="{{ asset('') }}vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('') }}vendor/themify-icons/themify-icons.css">
    <link rel="stylesheet" href="{{ asset('') }}vendor/perfect-scrollbar/css/perfect-scrollbar.css">

    <link rel="stylesheet" href="{{ asset('') }}vendor/izitoast/css/iziToast.min.css">


    <link href="{{ asset('') }}vendor/datatables.net-bs5/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <link href="{{ asset('') }}vendor/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css"
        rel="stylesheet" />

    <!-- CSS for this page only -->
    @stack('cssLibrary')
    {{-- <link rel="stylesheet" href="{{asset('')}}vendor/chart.js/Chart.min.css"> --}}
    <!-- End CSS  -->

    <link rel="stylesheet" href="{{ asset('') }}assets/css/style.min.css">
    <link rel="stylesheet" href="{{ asset('') }}assets/css/bootstrap-override.min.css">
    <link rel="stylesheet" id="theme-color" href="{{ asset('') }}assets/css/dark.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    @stack('css')
    <style>
        .dataTables_wrapper .dataTables_scroll {
    box-shadow: none !important;
}
    </style>
</head>

<body>
    <div id="app">
        {{-- <div class="shadow-header"></div> --}}
        {{-- @include('layouts.header') --}}
        @include('layouts.sidebar')

        {{-- <div class="modal fade" id="modal_action" tabindex="-1" aria-labelledby="largeModalLabel" aria-hidden="true"> --}}

        {{-- </div> --}}

        {{ $slot }}

        @include('layouts.setting')


        <div class="overlay action-toggle">
        </div>
    </div>
    <script src="{{ asset('') }}vendor/bootstrap/js/bootstrap.bundle.js"></script>
    <script src="{{ asset('') }}vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>

    <script src="{{ asset('') }}vendor/jquery/jquery.min.js"></script>
    <script src="{{ asset('') }}vendor/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="{{ asset('') }}vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('') }}vendor/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
    <script src="{{ asset('') }}vendor/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>
    <script src="{{ asset('') }}assets/js/pages/datatables.min.js"></script>

    <!-- js for this page only -->
    @stack('jsLibrary')

    <!-- ======= -->
    <script src="{{ asset('') }}assets/js/main.min.js"></script>
    <script>
        Main.init()
    </script>
    <script src="{{ asset('') }}vendor/izitoast/js/iziToast.min.js"></script>

    <script>
        function showMessage(status, message) {
            iziToast[status]({
                title: status == 'success' ? 'Success' : 'Error',
                message: message,
                position: 'topRight'
            });
        }


    </script>

    <script>
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                showMessage('error', "{{$error}}");
            @endforeach
        @endif

        @if(Session::has('status'))
            showMessage('success', '{{Session::get("message") ?? ''}}');
        @endisset
    </script>
    @stack('js')
</body>

</html>
