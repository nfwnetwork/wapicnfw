document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('ew-game-container');
    const canvas = document.createElement('canvas');
    canvas.width = 580;
    canvas.height = 300;
    container.appendChild(canvas);

    const info = document.createElement('div');
    info.id = 'ew-game-info';
    container.appendChild(info);

    const ctx = canvas.getContext('2d');
    const player = { x: 50, y: 150, size: 20, color: 'blue' };
    const rides = [
        { x: 100, y: 50 },
        { x: 300, y: 200 },
        { x: 450, y: 80 }
    ];
    let coins = [];
    let score = 0;
    let timer = 0;

    function spawnCoin() {
        const ride = rides[Math.floor(Math.random() * rides.length)];
        coins.push({
            x: ride.x + Math.random() * 40,
            y: ride.y + Math.random() * 40,
            size: 8
        });
    }

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        // background
        ctx.fillStyle = '#72b01d';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        // rides
        ctx.fillStyle = '#8e44ad';
        rides.forEach(r => {
            ctx.fillRect(r.x, r.y, 40, 40);
        });
        // coins
        ctx.fillStyle = '#f1c40f';
        coins.forEach(c => {
            ctx.beginPath();
            ctx.arc(c.x, c.y, c.size, 0, Math.PI * 2);
            ctx.fill();
        });
        // player
        ctx.fillStyle = player.color;
        ctx.fillRect(player.x, player.y, player.size, player.size);
    }

    function update() {
        coins = coins.filter(c => {
            const dist = Math.hypot(c.x - player.x, c.y - player.y);
            if (dist < player.size) {
                score++;
                return false;
            }
            return true;
        });
    }

    document.addEventListener('keydown', e => {
        const step = 5;
        if (e.key === 'ArrowUp') player.y -= step;
        if (e.key === 'ArrowDown') player.y += step;
        if (e.key === 'ArrowLeft') player.x -= step;
        if (e.key === 'ArrowRight') player.x += step;
    });

    setInterval(() => {
        spawnCoin();
        timer++;
        info.textContent = `Münzen: ${score} | Zeit: ${timer}s`;
    }, 1000);

    function loop() {
        update();
        draw();
        requestAnimationFrame(loop);
    }

    spawnCoin();
    loop();
});
