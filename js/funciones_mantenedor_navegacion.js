$(function () {
    const paginas = $('#mantenedorNavegacion').data('tipo') === 'pagina';
    const endpoint = '../controller/' + (paginas ? 'pagina' : 'menu') + '.php';
    const modal = new bootstrap.Modal(document.getElementById('reg_edi'));
    let registroId = 0, guardando = false;
    const texto = $.fn.dataTable.render.text();
    const escapar = value => $('<span>').text(value || '').html();
    const claseIcono = value => {
        if (!/^[a-zA-Z0-9_ -]+$/.test(value || '')) return 'far fa-circle';
        if (/(^| )icon-[a-z0-9-]+( |$)/.test(value)) return value.replace(/(^| )nav-icon(?= |$)/g, ' ').trim();
        if (value === 'uil-schedule') return 'far fa-calendar-alt';
        if (!/(^| )fa[srb]?( |$)/.test(value) && /(^| )fa-/.test(value)) return 'fas ' + value;
        return /(^| )fa[srb]?( |$)/.test(value) ? value : 'far fa-file-alt';
    };
    function preview() { $('#iconoPreview').attr('class', claseIcono($('#registroIcono').val())); }
    function error(mensaje) { return Swal.fire({icon:'error',title:'No se pudo completar',text:mensaje}); }
    const columnas = [{data:'descripcion',render:texto}];
    if (paginas) columnas.push({data:'menu',render:texto},{data:'ruta',render:texto});
    columnas.push({data:'icono',render:(value,type) => type === 'display' ? '<i class="' + claseIcono(value) + ' me-2" aria-hidden="true"></i><span>' + escapar(value) + '</span>' : value},
        {data:'orden'}, {data:'estado',render:(value,type) => type === 'display' ? '<span class="badge ' + (Number(value) === 1 ? 'bg-success' : 'bg-secondary') + '">' + (Number(value) === 1 ? 'Habilitado' : 'Deshabilitado') + '</span>' : value});
    const tabla = $('#tablaListado').DataTable({
        ajax:{url:endpoint,type:'POST',data:{task:1},dataSrc:res => Array.isArray(res) ? res : [],error:() => error('No se pudo cargar el listado.')},
        columns:columnas, pageLength:25, order:[[paginas ? 4 : 2,'asc']],
        language:{search:'Buscar:',lengthMenu:'Mostrar _MENU_ registros',info:'Mostrando _START_ a _END_ de _TOTAL_ registros',infoEmpty:'Sin registros',zeroRecords:'Sin resultados',emptyTable:'No hay registros',paginate:{previous:'Anterior',next:'Siguiente'}},
        createdRow:row => $(row).find('td').attr({tabindex:0,title:'Clic para editar'}).css('cursor','pointer')
    });
    let menusListos = Promise.resolve();
    if (paginas) menusListos = $.post('../controller/menu.php',{task:1},null,'json').then(res => {
        (Array.isArray(res) ? res : []).forEach(menu => $('#registroMenu').append(new Option(menu.descripcion + (Number(menu.estado) === 1 ? '' : ' (deshabilitado)'),menu.id)));
    });
    $('#registroIcono').on('input',preview);
    $('#nuevoRegistro').on('click',async function () {
        try { await menusListos; } catch (_) { error('No se pudieron cargar los menús.'); return; }
        registroId = 0; document.getElementById('formNavegacion').reset();
        $('#registroIcono').val(paginas ? 'far fa-file-alt' : 'fas fa-bars');
        $('#nombre_opcion').text(paginas ? 'Nueva página' : 'Nuevo menú'); preview(); modal.show();
    });
    $('#tablaListado').on('click keydown','tbody td',async function (event) {
        if (event.type === 'keydown' && !['Enter',' '].includes(event.key)) return;
        const row = tabla.row($(this).closest('tr')).data(); if (!row || guardando) return;
        event.preventDefault();
        try {
            await menusListos;
            const res = await $.post(endpoint,{task:2,[paginas ? 'pag_id' : 'men_id']:row.id},null,'json');
            if (!Array.isArray(res) || !res[0]) throw new Error();
            const data = res[0], prefix = paginas ? 'pag_' : 'men_'; registroId = row.id;
            $('#registroNombre').val(data[paginas ? 'pag_nombre' : 'men_descripcion']);
            $('#registroIcono').val(data[prefix+'icono']); $('#registroOrden').val(data[prefix+'orden']); $('#registroEstado').val(data[prefix+'estado']);
            if (paginas) { $('#registroMenu').val(data.men_id); $('#registroRuta').val(data.pag_ruta); }
            $('#nombre_opcion').text(paginas ? 'Editar página' : 'Editar menú'); preview(); modal.show();
        } catch (_) { error('No se pudieron cargar los datos del registro.'); }
    });
    $('#formNavegacion').on('submit',async function (event) {
        event.preventDefault(); if (guardando) return;
        const nombre = $('#registroNombre').val().trim(), icono = $('#registroIcono').val().trim();
        if (!nombre) { AvisoValidacionIFact('Ingrese el Nombre.', '#registroNombre'); return; }
        if (paginas && !$('#registroMenu').val()) { AvisoValidacionIFact('Seleccione un Menú.', '#registroMenu'); return; }
        if (paginas && !/^[a-zA-Z0-9_./-]{1,50}$/.test($('#registroRuta').val().trim())) { AvisoValidacionIFact('Ingrese una Ruta válida de hasta 50 caracteres.', '#registroRuta'); return; }
        if (!/^[a-zA-Z0-9_ -]{1,50}$/.test(icono)) { AvisoValidacionIFact('Ingrese una clase de Icono válida de hasta 50 caracteres.', '#registroIcono'); return; }
        const orden = $('#registroOrden').val();
        if (!/^\d+$/.test(orden) || !Number.isSafeInteger(Number(orden))) { AvisoValidacionIFact('Ingrese un Orden entero mayor o igual a cero.', '#registroOrden'); return; }
        guardando = true; $('#guardarNavegacion').prop('disabled',true);
        try {
            if (!await ConfirmarGuardadoIFact(registroId ? 'actualizar' : 'registrar',paginas ? 'página' : 'menú')) return;
            const prefix = paginas ? 'pag_' : 'men_';
            const data = {task:registroId ? 3 : 4,[prefix+'id']:registroId,[paginas ? 'pag_nombre' : 'men_descripcion']:nombre,[prefix+'icono']:icono,[prefix+'orden']:$('#registroOrden').val(),[prefix+'estado']:$('#registroEstado').val()};
            if (paginas) { data.men_id = $('#registroMenu').val(); data.pag_ruta = $('#registroRuta').val().trim(); }
            const res = await $.post(endpoint,data,null,'json'); if (Number(res) !== 1) throw new Error();
            modal.hide(); tabla.ajax.reload(null,false); AvisoIFact('Registro ' + (registroId ? 'actualizado' : 'creado') + ' correctamente.');
        } catch (_) { error('No se pudo guardar el registro.'); }
        finally { guardando = false; $('#guardarNavegacion').prop('disabled',false); }
    });
});
