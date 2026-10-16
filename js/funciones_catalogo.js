/* Mantenimientos del catálogo: mismos modales y avisos comunes de iFact. */
window.Catalogo = (() => {
    const tipo = document.body.dataset.catalogo;
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const language = {search:'Buscar:',lengthMenu:'Mostrar _MENU_ registros',info:'Mostrando _START_ a _END_ de _TOTAL_ registros',infoEmpty:'Sin registros',infoFiltered:'(de _MAX_ registros)',zeroRecords:'No se encontraron registros',emptyTable:'No hay registros',loadingRecords:'Cargando…',paginate:{first:'Primero',last:'Último',next:'Siguiente',previous:'Anterior'}};
    async function api(tipo, action, datos = {}) {
        const result = await $.ajax({url:'../controller/catalogo.php',method:'POST',dataType:'json',data:{...datos,tipo,action,csrf:document.body.dataset.csrf}});
        if (result.status !== 1) throw new Error(result.message || 'No se pudo completar la operación.');
        return result;
    }
    function error(err) { return Swal.fire({icon:'error',title:'No se pudo completar',text:err.responseJSON?.message || err.message || 'Error de conexión. Intente nuevamente.'}); }
    function estado(value) { return `<span class="badge ${Number(value) === 1 ? 'bg-success' : 'bg-secondary'}">${Number(value) === 1 ? 'Habilitado' : 'Deshabilitado'}</span>`; }
    function acciones(id, valores = false) {
        return `<div class="catalogo-acciones" role="group" aria-label="Acciones del registro">${valores ? `<button type="button" class="catalogo-action catalogo-action-valores" title="Administrar valores" data-accion="valores" data-id="${id}"><i aria-hidden="true" class="fa fa-layer-group"></i><span>Valores</span></button>` : ''}
            <button type="button" class="catalogo-action catalogo-action-eliminar" title="Eliminar registro" aria-label="Eliminar" data-accion="eliminar" data-id="${id}"><i aria-hidden="true" class="fa fa-trash-alt"></i></button></div>`;
    }
    function abrir(html, id) {
        return `<button type="button" class="catalogo-registro" title="Abrir registro" data-accion="editar" data-id="${Number(id)}">${html}</button>`;
    }
    function validar(form) {
        const input = [...form.elements].find(e => e.willValidate && !e.checkValidity());
        if (!input) return true;
        const label = form.querySelector(`label[for="${input.id}"]`)?.textContent.replace('*','').trim() || 'los datos del formulario';
        AvisoValidacionIFact(`Revise ${label}. ${input.validationMessage}`, input.id ? '#'+input.id : null);
        return false;
    }
    async function eliminar(tipo, id, nombre, reload) {
        const choice = await Swal.fire({icon:'question',title:'Eliminar registro',text:`¿Quieres retirar ${nombre} del catálogo? Los registros relacionados se conservarán.`,showCancelButton:true,confirmButtonText:'Eliminar',cancelButtonText:'Cancelar'});
        if (!choice.isConfirmed) return;
        try { const r = await api(tipo,'eliminar',{id}); await AvisoIFact(r.message); reload(); } catch(e) { error(e); }
    }
    return {tipo,esc,language,api,error,estado,acciones,abrir,validar,eliminar};
})();

$(function () {
    const C = Catalogo;
    if (C.tipo === 'producto') return;
    const configs = {
        categoria:{pk:'cat_id',nombre:'cat_nombre',estado:'cat_estado',label:'categoría',campos:[['cat_nombre','Nombre',100,true],['cat_descripcion','Descripción',500,false,'textarea']]},
        unidad:{pk:'und_id',nombre:'und_nombre',estado:'und_estado',label:'unidad de medida',campos:[['und_nombre','Nombre',20,true],['und_abrev','Abreviatura visible (ej. UND)',5,true],['und_codigo_sunat','Código SUNAT (ej. NIU)',3,false],['und_decimales','Precisión de cantidad (no es stock)',0,false,'decimales']]},
        atributo:{pk:'atr_id',nombre:'atr_nombre',estado:'atr_estado',label:'atributo',campos:[['atr_nombre','Nombre',80,true]]},
        valor:{pk:'ava_id',nombre:'ava_nombre',estado:'ava_estado',label:'valor',campos:[['ava_nombre','Valor',80,true],['ava_color','Color visual (opcional, #RRGGBB)',7,false]]}
    };
    let current = C.tipo, padre = 0, parentName = '', id = 0, busy = false;
    let tabla, tablaValores;
    const cfg = () => configs[current];
    const columns = [
        {data:configs[C.tipo].nombre,render:(d,t,r)=>t==='display'?C.abrir(C.esc(d),r[configs[C.tipo].pk]):d},
        ...(C.tipo === 'unidad' ? [
            {data:'und_abrev',render:$.fn.dataTable.render.text()},
            {data:'und_codigo_sunat',defaultContent:'—',render:$.fn.dataTable.render.text()},
            {data:'und_decimales',render:d=>Number(d) === 0 ? 'Entera' : `${Number(d)} decimales`}
        ] : []),
        {data:configs[C.tipo].estado,render:(d,t,r)=>t==='display'?C.abrir(C.estado(d),r[configs[C.tipo].pk]):d},
        {data:null,orderable:false,searchable:false,render:r=>C.acciones(r[configs[C.tipo].pk],C.tipo==='atributo')}
    ];
    function source(type, parent = 0) {
        return (_, callback) => {
            C.api(type,'listar',{padre:parent}).then(r=>callback({data:r.data}))
                .catch(e=> { callback({data:[]}); C.error(e); });
        };
    }
    tabla = $('#tablaCatalogo').DataTable({ajax:source(C.tipo),columns,language:C.language,pageLength:25});
    function reload() { if (current==='valor') tablaValores.ajax.reload(null,false); else tabla.ajax.reload(null,false); }
    function form(row = {}) {
        id = Number(row[cfg().pk] || 0);
        $('#nombre_opcion').text(`${id ? 'Editar' : 'Registrar'} ${cfg().label}${current==='valor' ? ' · '+parentName : ''}`);
        $('#maestroCampos').html(cfg().campos.map(([key,label,max,required,kind])=> {
            const value = C.esc(row[key] ?? '');
            let input;
            if (key==='ava_color') {
                const chosen=/^#[0-9a-f]{6}$/i.test(row[key] || '') ? row[key] : '';
                label='Color visual (opcional)';
                input=`<input type="hidden" id="ava_color" value="${C.esc(chosen)}"><div class="catalogo-color-selector"><input type="color" id="ava_color_paleta" value="${C.esc(chosen || '#5758ff')}" aria-label="Seleccionar color visual"><span id="colorSeleccionado">${chosen?C.esc(chosen.toUpperCase()):'Sin color'}</span><button type="button" class="catalogo-color-quitar" id="quitarColor">Quitar</button></div>`;
            }
            else if (kind==='textarea') input=`<textarea id="${key}" class="form-control" maxlength="${max}" rows="3">${value}</textarea>`;
            else if (kind==='decimales') input=`<select id="${key}" class="form-select">${[0,1,2,3].map(n=>`<option value="${n}" ${Number(row[key]||0)===n?'selected':''}>${n===0?'Cantidad entera':n+' decimales'}</option>`).join('')}</select>`;
            else input=`<input id="${key}" class="form-control" maxlength="${max}" value="${value}" ${required?'required':''} ${key==='ava_color'?'placeholder="#000000"':''}>`;
            return `<div class="col-md-${kind==='textarea'?12:6}"><label class="form-label-custom" for="${key==='ava_color'?'ava_color_paleta':key}">${C.esc(label)} ${required?'<span class="text-danger">*</span>':''}</label>${input}</div>`;
        }).join('')+`<div class="col-12"><label class="form-label-custom">Estado</label><div class="ifact-state-options">${[1,0].map(n=>`<label><input type="radio" name="masterEstado" value="${n}" ${Number(row[cfg().estado]??1)===n?'checked':''}><span><i aria-hidden="true" class="${n?'far fa-check-circle':'fas fa-ban'}"></i> ${n?'Habilitado':'Deshabilitado'}</span></label>`).join('')}</div></div>`);
        $('#reg_edi').modal('show');
    }
    $('#catalogoNuevo,#valorNuevo').on('click',()=>form());
    function seleccionarColor(color) {
        $('#ava_color').val(color);
        $('#colorSeleccionado').text(color ? color.toUpperCase() : 'Sin color');
        if (color) $('#ava_color_paleta').val(color);
    }
    $('#maestroCampos').on('input change','#ava_color_paleta',function () { seleccionarColor(this.value); });
    $('#maestroCampos').on('click','#quitarColor',()=>seleccionarColor(''));
    $('#volverAtributos').on('click',()=> { current=C.tipo; padre=0; $('#catalogoValores').prop('hidden',true); $('#catalogoLista,#catalogoNuevo').prop('hidden',false); $('#atributosAyudaTitulo').text('Define las opciones de tus productos'); $('#atributosAyudaTexto').text('Crea atributos como Color o Talla. Pulsa Valores para registrar sus opciones, como Azul o 38; después combínalas en Productos.'); });
    $(document).on('click','#tablaCatalogo button[data-accion],#tablaValores button[data-accion]',async function () {
        if (busy) return;
        const action=this.dataset.accion, recordId=Number(this.dataset.id);
        const type=$(this).closest('table').attr('id')==='tablaValores'?'valor':C.tipo;
        try {
            if (action==='eliminar') { const r=(await C.api(type,'obtener',{id:recordId})).data; return C.eliminar(type,recordId,r[configs[type].nombre],reload); }
            const r=(await C.api(type,'obtener',{id:recordId})).data;
            if (action==='valores') {
                padre=recordId; parentName=r.atr_nombre; current='valor';
                $('#valoresTitulo').text(`Valores de ${parentName}`); $('#catalogoLista,#catalogoNuevo').prop('hidden',true); $('#catalogoValores').prop('hidden',false);
                $('#atributosAyudaTitulo').text(`Opciones de ${parentName}`);
                $('#atributosAyudaTexto').text('Pulsa Nuevo valor para registrar cada opción de este atributo. Las opciones habilitadas estarán disponibles al crear las combinaciones de un producto.');
                if (tablaValores) tablaValores.destroy();
                tablaValores=$('#tablaValores').DataTable({ajax:source('valor',padre),columns:[
                    {data:'ava_nombre',render:(d,t,r)=>t==='display'?C.abrir(C.esc(d),r.ava_id):d},
                    {data:'ava_color',render:d=>/^#[a-f0-9]{6}$/i.test(d||'')?`<span class="catalogo-color" style="background:${d}"></span>${C.esc(d)}`:'—'},
                    {data:'ava_estado',render:(d,t,r)=>t==='display'?C.abrir(C.estado(d),r.ava_id):d},
                    {data:null,orderable:false,searchable:false,render:r=>C.acciones(r.ava_id)}
                ],language:C.language,pageLength:25});
            } else if (action==='editar') { current=type; form(r); }
        } catch(e) { C.error(e); }
    });
    $('#catalogoForm').attr('novalidate',true).on('submit',async e=> {
        e.preventDefault(); if (busy || !C.validar(e.target)) return;
        busy=true; $('#btn_guardar').prop('disabled',true);
        const data={id,atr_id:padre};
        cfg().campos.forEach(([key])=>data[key]=$('#'+key).val().trim());
        data[cfg().estado]=$('[name=masterEstado]:checked').val();
        if (current==='unidad') { data.und_abrev=data.und_abrev.toUpperCase(); data.und_codigo_sunat=data.und_codigo_sunat.toUpperCase(); }
        try {
            if (!await ConfirmarGuardadoIFact(id?'actualizar':'registrar',cfg().label)) return;
            const r=await C.api(current,'guardar',data); $('#reg_edi').modal('hide'); reload(); await AvisoIFact(r.message);
        } catch(err) { C.error(err); }
        finally { busy=false; $('#btn_guardar').prop('disabled',false); }
    });
});
