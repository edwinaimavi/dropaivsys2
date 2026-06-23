<html>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CICO Ingenieros</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {

            min-height: 100vh;

            background:
                radial-gradient(circle at top left,
                    rgba(34, 197, 94, .15),
                    transparent 35%),

                radial-gradient(circle at bottom right,
                    rgba(59, 130, 246, .15),
                    transparent 35%),

                #f5f7fb;

            overflow-x: hidden;
        }

        .bg-animation {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .15;
            background-image:
                linear-gradient(#16a34a 1px, transparent 1px),
                linear-gradient(90deg, #16a34a 1px, transparent 1px);

            background-size: 70px 70px;
        }

        .container {
            max-width: 1600px;
            margin: auto;
            padding: 40px;
        }

        .hero {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 40px;

            align-items: center;

            min-height: 100vh;
        }

        .glass {

            background: rgba(255, 255, 255, .65);

            backdrop-filter: blur(20px);

            border: 1px solid rgba(255, 255, 255, .7);

            border-radius: 35px;

            box-shadow:
                0 25px 50px rgba(0, 0, 0, .08);
        }

        .left-side {
            padding: 30px;
        }

        .logo-box {

            width: 320px;

            padding: 20px;

            margin-bottom: 30px;
        }

        .logo-box img {

            width: 100%;

            border-radius: 20px;
        }

        .greeting {

            font-size: 22px;

            font-weight: 700;

            color: #16a34a;

            margin-bottom: 15px;
        }

        h1 {

            font-size: 65px;

            line-height: 1.1;

            margin-bottom: 20px;

            color: #0f172a;
        }

        h1 span {
            color: #16a34a;
        }

        .description {

            font-size: 20px;

            color: #64748b;

            line-height: 1.9;

            max-width: 700px;
        }

        .btn-login {

            display: inline-block;

            margin-top: 35px;

            padding: 16px 35px;

            background: #16a34a;

            color: white;

            text-decoration: none;

            border-radius: 18px;

            font-weight: 700;

            transition: .3s;
        }

        .btn-login:hover {

            transform: translateY(-3px);

            box-shadow:
                0 15px 30px rgba(22, 163, 74, .3);
        }

        .stats {

            margin-top: 35px;

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 15px;
        }

        .stat {

            padding: 20px;

            text-align: center;
        }

        .stat-number {

            font-size: 35px;

            font-weight: 800;

            color: #16a34a;
        }

        .stat-text {

            color: #64748b;

            margin-top: 5px;
        }

        .right-side {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .clock-card {

            padding: 40px;
        }

        .clock {

            display: flex;

            justify-content: center;

            align-items: center;

            gap: 20px;
        }

        .clock-box {

            width: 180px;

            height: 180px;

            background: #0f172a;

            border-radius: 30px;

            display: flex;

            justify-content: center;

            align-items: center;

            position: relative;

            box-shadow:
                0 20px 40px rgba(0, 0, 0, .2);
        }

        .clock-box::after {

            content: '';

            position: absolute;

            width: 100%;

            height: 2px;

            background: rgba(255,255,255,.15);

            top: 50%;
        }

        .clock-box span {

            font-size: 110px;

            color: white;

            font-weight: 800;
        }

        .separator {

            font-size: 80px;

            color: #16a34a;

            font-weight: 800;
        }

        .date {

            margin-top: 20px;

            text-align: center;

            color: #64748b;

            font-size: 18px;
        }

        .news-card,
        .quick-card {

            padding: 30px;
        }

        .section-title {

            font-size: 24px;

            font-weight: 700;

            margin-bottom: 20px;

            color: #0f172a;
        }

        .news-item {

            padding: 15px;

            margin-bottom: 12px;

            background: rgba(22,163,74,.08);

            border-radius: 15px;

            transition: .3s;
        }

        .news-item:hover {

            transform: translateX(5px);
        }

        .modules {

            display: grid;

            grid-template-columns: repeat(4, 1fr);

            gap: 15px;
        }

        .module {

            text-align: center;

            padding: 25px;

            border-radius: 20px;

            background: white;

            text-decoration: none;

            color: #0f172a;

            transition: .3s;
        }

        .module:hover {

            transform: translateY(-5px);

            box-shadow:
                0 15px 30px rgba(22,163,74,.2);
        }

        .module-icon {

            font-size: 40px;

            margin-bottom: 10px;
        }

        @media(max-width:1200px){

            .hero{
                grid-template-columns:1fr;
            }

            h1{
                font-size:45px;
            }

            .clock-box{
                width:120px;
                height:120px;
            }

            .clock-box span{
                font-size:70px;
            }

            .modules{
                grid-template-columns:repeat(2,1fr);
            }
        }
    </style>
</head>

<body>

<div class="bg-animation"></div>

<div class="container">

<div class="hero">

<div class="left-side">

<div class="logo-box glass">

<img src="{{ asset('vendor/adminlte/dist/img/cico.jpeg') }}">

</div>

<div id="greeting" class="greeting">
☀️ Buenos días
</div>

<h1>
Bienvenido a
<span>CICO Ingenieros</span>
</h1>

<p class="description">
Soluciones en desarrollo de software, infraestructura tecnológica,
seguridad informática, videovigilancia, soporte TI y transformación digital.
</p>

@auth

<a href="/admin" class="btn-login">
Ingresar al Sistema
</a>

@else

<a href="{{ route('login') }}" class="btn-login">
Iniciar Sesión
</a>

@endauth

<div class="stats">

<div class="stat glass">
<div class="stat-number">24/7</div>
<div class="stat-text">Soporte</div>
</div>

<div class="stat glass">
<div class="stat-number">100%</div>
<div class="stat-text">Compromiso</div>
</div>

<div class="stat glass">
<div class="stat-number">+500</div>
<div class="stat-text">Clientes</div>
</div>

<div class="stat glass">
<div class="stat-number">+10</div>
<div class="stat-text">Años</div>
</div>

</div>

</div>

<div class="right-side">

<div class="clock-card glass">

<div class="clock">

<div class="clock-box">
<span id="hours">00</span>
</div>

<div class="separator">:</div>

<div class="clock-box">
<span id="minutes">00</span>
</div>

</div>

<div class="date" id="currentDate"></div>

</div>

<div class="news-card glass">

<div class="section-title">
🚀 Noticias Tecnológicas
</div>

<div id="newsContainer"></div>

</div>

<div class="quick-card glass">

<div class="section-title">
⚡ Accesos Rápidos
</div>

<div class="modules">

<div class="module">
<div class="module-icon">👥</div>
Usuarios
</div>

<div class="module">
<div class="module-icon">💻</div>
Sistemas
</div>

<div class="module">
<div class="module-icon">📊</div>
Reportes
</div>

<div class="module">
<div class="module-icon">🔒</div>
Seguridad
</div>

</div>

</div>

</div>

</div>

</div>

<script>

function updateClock(){

    const now=new Date();

    let h=String(now.getHours()).padStart(2,'0');
    let m=String(now.getMinutes()).padStart(2,'0');

    document.getElementById('hours').innerHTML=h;
    document.getElementById('minutes').innerHTML=m;

    let saludo='';

    if(now.getHours()<12){
        saludo='☀️ Buenos días';
    }else if(now.getHours()<18){
        saludo='🌤️ Buenas tardes';
    }else{
        saludo='🌙 Buenas noches';
    }

    document.getElementById('greeting').innerHTML=saludo;

    document.getElementById('currentDate').innerHTML=
        now.toLocaleDateString('es-PE',{
            weekday:'long',
            day:'numeric',
            month:'long',
            year:'numeric'
        });

}

setInterval(updateClock,1000);
updateClock();

const news=[

'🤖 OpenAI sigue revolucionando la inteligencia artificial empresarial.',

'🔐 La ciberseguridad continúa siendo prioridad mundial.',

'☁️ La nube híbrida lidera las nuevas infraestructuras TI.',

'🚀 Laravel sigue siendo uno de los frameworks PHP más utilizados.',

'📈 La automatización aumenta la productividad empresarial.',

'💡 CICO Ingenieros impulsa la innovación tecnológica en Perú.'

];

function renderNews(){

    let html='';

    news.forEach(function(item){

        html+=`
            <div class="news-item">
                ${item}
            </div>
        `;
    });

    document.getElementById('newsContainer').innerHTML=html;
}

renderNews();

</script>

</body>
</html>
