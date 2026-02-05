</div><!-- /container -->
<footer>
<div class="container">
<span><i class="bi bi-shield-fill"></i> <?=obtenerConfig('empresa_nombre','Sistema de Tickets')?> — Panel Admin &copy; <?=date('Y')?></span>
</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function abrirVisor(url) {
    document.getElementById('visorImgSrc').src = url;
    document.getElementById('visorImg').classList.add('activo');
}
function cerrarVisor() {
    document.getElementById('visorImg').classList.remove('activo');
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') cerrarVisor(); });
</script>
</body>
</html>
