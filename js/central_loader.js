(() => {
  const labels = {
    'dashboard_central.php':['Cargando inicio','Preparando el centro de operaciones.'],
    'empresas_central.php':['Cargando empresas','Trayendo empresas, locales y series.'],
    'comprobantes_central.php':['Consultando comprobantes','Recuperando la información tributaria.'],
    'usuarios_central.php':['Cargando usuarios','Preparando los accesos del panel.'],
    'logout_central.php':['Cerrando sesión','Finalizando tu sesión de forma segura.']
  };
  let loader;
  const ensure = () => {
    if (loader) return loader;
    loader = document.createElement('div');
    loader.className = 'central-loader';
    loader.setAttribute('aria-hidden','true');
    loader.innerHTML = '<section class="central-loader-card" role="status" aria-live="polite"><div class="central-loader-brand"><img src="../assets/img/intelsoft-logo-horizontal.png" alt="Intelsoft Perú"><span class="central-loader-state"><i></i> Procesando</span></div><div class="central-loader-content"><div class="central-loader-spinner"><span></span></div><div class="central-loader-copy"><h2 class="central-loader-title">Cargando información</h2><p class="central-loader-message">Espera un momento mientras preparamos todo.</p></div></div><div class="central-loader-progress"><span></span></div></section>';
    document.body.appendChild(loader);
    return loader;
  };
  const show = (title='Cargando información', message='Espera un momento mientras preparamos todo.') => {
    const element=ensure();
    element.querySelector('.central-loader-title').textContent=title;
    element.querySelector('.central-loader-message').textContent=message;
    element.classList.add('visible');
    element.setAttribute('aria-hidden','false');
  };
  const hide = () => { if(loader){loader.classList.remove('visible');loader.setAttribute('aria-hidden','true');} };
  window.CentralLoader={show,hide};
  document.addEventListener('click',event=>{
    if(event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
    const link=event.target.closest('a[href]');
    if(!link||link.target==='_blank'||link.hasAttribute('download')||link.closest('#documentHistoryShell')||link.classList.contains('filter-clear'))return;
    const href=link.getAttribute('href')||'';
    if(!href||href[0]==='#'||href.startsWith('javascript:')||link.dataset.bsToggle)return;
    const file=href.split('?')[0].split('/').pop();
    const copy=labels[file]||['Cargando información','Abriendo la sección solicitada.'];
    show(copy[0],copy[1]);
  });
  document.addEventListener('submit',event=>{
    if(event.defaultPrevented||event.target?.id==='documentFilterForm')return;
    const submitter=event.submitter;
    const label=(submitter?.textContent||'').trim().toLowerCase();
    const title=label.includes('sesión')?'Iniciando sesión':label.includes('guardar')?'Guardando cambios':label.includes('crear')?'Registrando información':label.includes('token')?'Actualizando token':'Procesando solicitud';
    show(title,'Estamos validando y guardando la información de forma segura.');
  });
  window.addEventListener('pageshow',hide);
})();
