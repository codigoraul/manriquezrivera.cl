// Redirección automática si el servicio de abogados existe
fetch('/wp-json/wp/v2/servicio?slug=abogados')
  .then(response => response.json())
  .then(services => {
    if (services && services.length > 0) {
      window.location.href = '/servicios/abogados';
    }
  })
  .catch(() => {
    // Si hay error, redirigir después de 3 segundos
    setTimeout(() => {
      window.location.href = '/servicios/abogados';
    }, 3000);
  });
