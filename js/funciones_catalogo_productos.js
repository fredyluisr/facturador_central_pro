$(function () {
    const C=Catalogo;
    let options, productoId=0, version='', rows=[], selectors=[], busy=false, skuBase='',productoImagen=null,productoImagenFile=null,productoImagenPreview=null,eliminarProductoImagen=false;
    const byValue=id=>options.valores.find(v=>Number(v.ava_id)===Number(id));
    const byAttr=id=>options.atributos.find(a=>Number(a.atr_id)===Number(id));
    const attrIds=values=>values.map(byValue).filter(Boolean).map(v=>Number(v.atr_id)).sort((a,b)=>a-b).join(',');
    const combo=values=>[...values].map(Number).sort((a,b)=>Number(byValue(a)?.atr_id)-Number(byValue(b)?.atr_id)).join('-') || 'simple';
    const description=values=>values.length ? values.map(id=> { const v=byValue(id); return v ? `${byAttr(v.atr_id)?.atr_nombre || 'Atributo'}: ${v.ava_nombre}` : 'Valor retirado'; }).join(' / ') : 'Simple (sin atributos)';
    const tabla=$('#tablaCatalogo').DataTable({ajax:(_,callback)=> {
        C.api('producto','listar').then(r=>callback({data:r.data})).catch(e=> { callback({data:[]}); C.error(e); });
    },columns:[
        {data:'pro_nombre',render:(d,t,r)=>t==='display'?C.abrir(C.esc(d),r.pro_id):d},
        {data:'cat_nombre',render:d=>d ? C.esc(d) : '<span class="text-muted">Por clasificar</span>'},
        {data:'und_abrev',render:$.fn.dataTable.render.text()},
        {data:'pro_prec_venta',render:d=>Number(d).toFixed(2)},
        {data:null,render:r=>`${Number(r.variantes_activas||0)} habilitadas / ${Number(r.variantes)} total`},
        {data:'pro_estado',render:(d,t,r)=>t==='display'?C.abrir(C.estado(d),r.pro_id):d},
        {data:null,orderable:false,searchable:false,render:r=>C.acciones(r.pro_id)}
    ],language:C.language,pageLength:25,order:[],createdRow:(row,data)=>{
        $(row).addClass('catalogo-producto-fila').attr({
            tabindex:0,
            role:'button',
            'data-producto-id':Number(data.pro_id),
            'aria-label':`Editar ${data.pro_nombre}`
        });
    }});
    function selectOptions(list, idKey, nameKey, stateKey, selected) {
        return '<option value="">Seleccionar</option>'+list.filter(r=>Number(r[stateKey])===1 || Number(r[idKey])===Number(selected)).map(r=>`<option value="${Number(r[idKey])}" ${Number(r[idKey])===Number(selected)?'selected':''}>${C.esc(r[nameKey])}${Number(r[stateKey])!==1?' (deshabilitado)':''}</option>`).join('');
    }
    function renderSelectors() {
        $('#productoAtributos').html(selectors.map((s,i)=>`<div class="col-md-4"><div class="catalogo-atributo">
            <div class="d-flex gap-2"><select class="form-select selector-atributo" data-index="${i}" aria-label="Atributo ${i+1}">${selectOptions(options.atributos,'atr_id','atr_nombre','atr_estado',s.atr_id)}</select>
            <button class="btn btn-sm btn-light quitar-atributo" type="button" data-index="${i}" title="Quitar atributo" aria-label="Quitar atributo"><i aria-hidden="true" class="fa fa-times"></i></button></div>
            <div class="catalogo-checks">${options.valores.filter(v=>Number(v.atr_id)===Number(s.atr_id) && Number(v.ava_estado)===1).map(v=>`<label><input type="checkbox" class="selector-valor" data-index="${i}" value="${Number(v.ava_id)}" ${s.valores.includes(Number(v.ava_id))?'checked':''}>${C.esc(v.ava_nombre)}</label>`).join('') || (s.atr_id ? '<small class="text-muted">Este atributo aún no tiene valores habilitados. Regístralos en Catálogo → Variantes → Valores y luego pulsa «Actualizar atributos».</small>' : '<small class="text-muted">Selecciona un atributo para ver sus valores.</small>')}</div>
        </div></div>`).join(''));
        $('#agregarAtributo').prop('disabled',selectors.length>=3);
    }
    function renderRows() {
        $('#productoVariantes').html(rows.map((r,i)=>`<tr>
            <td>${C.esc(description(r.valores))}${r.pva_id?`<br><small class="text-muted">Variante #${Number(r.pva_id)}</small>`:''}</td>
            <td><input class="form-control var-sku" id="var_sku_${i}" data-index="${i}" value="${C.esc(r.pva_sku)}" maxlength="60" required aria-label="SKU de ${C.esc(description(r.valores))}"></td>
            <td><input class="form-control var-barras" data-index="${i}" value="${C.esc(r.pva_codigo_barras || '')}" maxlength="80" aria-label="Código de barras"></td>
            <td><input class="form-control var-precio" id="var_precio_${i}" type="number" data-index="${i}" value="${C.esc(r.pva_precio)}" min="0" max="9999999999.99" step="0.01" required aria-label="Precio de variante"></td>
            <td><label class="catalogo-switch"><input type="checkbox" role="switch" class="var-estado" data-index="${i}" ${Number(r.pva_estado)===1?'checked':''} aria-label="Habilitar variante ${C.esc(description(r.valores))}"><span class="catalogo-switch-pista" aria-hidden="true"></span><span class="catalogo-switch-texto">${Number(r.pva_estado)===1?'Activo':'Inactivo'}</span></label></td>
            <td>${r.pva_id ? '' : `<button type="button" class="catalogo-action catalogo-action-eliminar quitar-variante" data-index="${i}" title="Eliminar variante nueva" aria-label="Eliminar variante nueva"><i aria-hidden="true" class="fa fa-trash-alt"></i></button>`}</td>
        </tr>`).join(''));
        $('#variantesResumen').text(`${rows.length} variantes · ${rows.filter(r=>Number(r.pva_estado)===1).length} habilitadas`);
    }
    function newRow(values) {
        return {pva_id:0,valores:values,pva_sku:skuBase+(values.length ? '-'+combo(values) : ''),pva_codigo_barras:'',pva_precio:$('#pro_prec_venta').val() || '0.00',pva_estado:1,precioAuto:true};
    }
    async function open(id=0) {
        if (busy) return;
        busy=true; $('#catalogoNuevo').prop('disabled',true);
        try {
            options=(await C.api('producto','opciones')).data;
            const p=id ? (await C.api('producto','obtener',{id})).data : {};
            productoId=id; version=p.version || ''; selectors=[];
            const random=new Uint32Array(1); crypto.getRandomValues(random);
            skuBase=id ? `PRO-${id}` : 'P-'+random[0].toString(16).toUpperCase();
            $('#catalogoForm')[0].reset();
            $('#pro_nombre').val(p.pro_nombre || '');
            $('#pro_prec_venta').val(p.pro_prec_venta || '0.00'); $('#pro_estado').val(p.pro_estado ?? 1).trigger('change');
            $('#cat_id').html(selectOptions(options.categorias,'cat_id','cat_nombre','cat_estado',p.cat_id));
            const defaultUnit=options.unidades.find(u=>u.und_abrev==='UND' && Number(u.und_estado)===1)?.und_id;
            $('#und_med').html(selectOptions(options.unidades,'und_id','und_nombre','und_estado',p.und_med || defaultUnit));
            rows=p.variantes ? p.variantes.map(r=>({...r,valores:r.valores.map(Number)})) : [newRow([])];
            if(productoImagenPreview?.startsWith('blob:'))URL.revokeObjectURL(productoImagenPreview);
            productoImagen=p.pro_imagen||null;productoImagenFile=null;productoImagenPreview=null;eliminarProductoImagen=false;renderFotoProducto();
            const enabled=rows.filter(r=>Number(r.pva_estado)===1);
            const attributes=[...new Set((enabled[0]?.valores || []).map(id=>Number(byValue(id)?.atr_id)).filter(Boolean))];
            selectors=attributes.map(atr_id=>({atr_id,valores:[...new Set(enabled.flatMap(r=>r.valores).filter(id=>Number(byValue(id)?.atr_id)===atr_id))]}));
            renderSelectors(); renderRows(); $('#contenidoAtributos').collapse('hide'); $('#nombre_opcion').text(id ? 'Editar producto' : 'Registrar producto'); $('#reg_edi').modal('show');
        } catch(e) { C.error(e); }
        finally { busy=false; $('#catalogoNuevo').prop('disabled',false); }
    }
    $('#catalogoNuevo').on('click',()=>open());
    $('#actualizarAtributos').on('click',async function () {
        if (busy) return;
        busy=true; $(this).prop('disabled',true);
        try {
            options=(await C.api('producto','opciones')).data;
            renderSelectors();
        } catch(e) { C.error(e); }
        finally { busy=false; $(this).prop('disabled',false); }
    });
    $('#agregarAtributo').on('click',()=> { if (selectors.length<3) { selectors.push({atr_id:0,valores:[]}); renderSelectors(); } });
    $('#productoAtributos').on('change','.selector-atributo',function () { const i=Number(this.dataset.index); selectors[i]={atr_id:Number(this.value),valores:[]}; renderSelectors(); });
    $('#productoAtributos').on('change','.selector-valor',function () { const i=Number(this.dataset.index); selectors[i].valores=[...document.querySelectorAll(`.selector-valor[data-index="${i}"]:checked`)].map(el=>Number(el.value)); });
    $('#productoAtributos').on('click','.quitar-atributo',function () { selectors.splice(Number(this.dataset.index),1); renderSelectors(); });
    $('#productoVariantes').on('input change','input,select',function () {
        const row=rows[Number(this.dataset.index)];
        const key=this.classList.contains('var-sku')?'pva_sku':this.classList.contains('var-barras')?'pva_codigo_barras':this.classList.contains('var-precio')?'pva_precio':'pva_estado';
        row[key]=key==='pva_estado' ? (this.checked?1:0) : this.value;
        if (key==='pva_estado') $(this).siblings('.catalogo-switch-texto').text(this.checked?'Activo':'Inactivo');
        if (key==='pva_precio') row.precioAuto=false;
        $('#variantesResumen').text(`${rows.length} variantes · ${rows.filter(r=>Number(r.pva_estado)===1).length} habilitadas`);
    });
    $('#pro_prec_venta').on('input change',()=> {
        rows.forEach(r=> { if (!r.pva_id && r.precioAuto) r.pva_precio=$('#pro_prec_venta').val() || '0.00'; });
        renderRows();
    });
    $('#productoVariantes').on('click','.quitar-variante',function () { rows.splice(Number(this.dataset.index),1); renderRows(); });
    function fotoProductoUrl(){return productoImagenPreview||(productoImagen?'../'+productoImagen:'');}
    function renderFotoProducto(){const src=fotoProductoUrl();$('#verFotoProducto').html(src?`<img src="${C.esc(src)}" alt="Foto del producto">`:'<span><i class="far fa-image"></i><small>Sin foto</small></span>').prop('disabled',!src);$('#seleccionarFotoProducto').html(`<i class="fa fa-camera me-1"></i>${src?'Cambiar':'Seleccionar'}`);$('#quitarFotoProducto').prop('hidden',!src);}
    $('#seleccionarFotoProducto').on('click',()=>$('#archivoFotoProducto').trigger('click'));
    $('#archivoFotoProducto').on('change',function(){const file=this.files?.[0];if(!file)return;if(!['image/jpeg','image/png','image/webp','image/gif'].includes(file.type)){this.value='';return AvisoValidacionIFact('Selecciona una imagen JPG, PNG, WebP o GIF.');}if(file.size>10*1024*1024){this.value='';return AvisoValidacionIFact('La imagen debe pesar como máximo 10 MB antes de optimizarse.');}if(productoImagenPreview?.startsWith('blob:'))URL.revokeObjectURL(productoImagenPreview);productoImagenFile=file;productoImagenPreview=URL.createObjectURL(file);eliminarProductoImagen=false;renderFotoProducto();});
    $('#quitarFotoProducto').on('click',()=>{if(productoImagenPreview?.startsWith('blob:'))URL.revokeObjectURL(productoImagenPreview);productoImagenPreview=null;productoImagenFile=null;eliminarProductoImagen=Boolean(productoImagen);productoImagen=null;$('#archivoFotoProducto').val('');renderFotoProducto();});
    $('#verFotoProducto').on('click',()=>{const src=fotoProductoUrl();if(!src)return;$('#tituloImagenVariante').text($('#pro_nombre').val()||'Foto del producto');$('#imagenVarianteAmpliada').attr('src',src);$('#modalImagenVariante').modal('show');});
    async function imagenApi(action,id,file=null) {
        const fd=new FormData(); fd.append('tipo','producto'); fd.append('action',action); fd.append('id',id); fd.append('csrf',document.body.dataset.csrf); if(file)fd.append('imagen',file);
        const result=await $.ajax({url:'../controller/catalogo.php',method:'POST',dataType:'json',data:fd,processData:false,contentType:false});
        if(result.status!==1)throw new Error(result.message||'No se pudo guardar la imagen.'); return result;
    }
    $('#generarVariantes').on('click',async ()=> {
        if (busy) return;
        if (!selectors.length || selectors.some(s=>!s.atr_id || !s.valores.length)) return AvisoValidacionIFact('Selecciona al menos un atributo y un valor por atributo.');
        if (new Set(selectors.map(s=>s.atr_id)).size!==selectors.length) return AvisoValidacionIFact('No selecciones el mismo atributo más de una vez.');
        let combinations=[[]];
        for (const s of selectors) {
            if (combinations.length*s.valores.length>300) return AvisoValidacionIFact('Genera como máximo 300 combinaciones por producto.');
            combinations=combinations.flatMap(c=>s.valores.map(v=>[...c,v]));
        }
        const requested=selectors.map(s=>s.atr_id).sort((a,b)=>a-b).join(',');
        const old=rows.filter(r=>r.pva_id || attrIds(r.valores)===requested);
        const additions=combinations.filter(c=>!old.some(r=>combo(r.valores)===combo(c)));
        if (old.length+additions.length>300) return AvisoValidacionIFact('El producto superaría 300 variantes, incluyendo las anteriores.');
        busy=true;
        try {
            const choice=await Swal.fire({icon:'question',title:'Generar variantes',text:`Se agregarán ${additions.length} combinaciones. Las variantes de otros atributos se deshabilitarán; las filas nuevas incompatibles se retirarán. Los códigos y precios existentes se conservarán.`,showCancelButton:true,confirmButtonText:'Generar',cancelButtonText:'Cancelar'});
            if (!choice.isConfirmed) return;
            rows=old.map(r=>attrIds(r.valores)!==requested?{...r,pva_estado:0}:r);
            rows.push(...additions.map(newRow)); renderRows();
        } finally { busy=false; }
    });
    $('#varianteSimple').on('click',async ()=> {
        if (busy) return;
        busy=true;
        try {
            const choice=await Swal.fire({icon:'question',title:'Usar variante simple',text:'Las variantes con atributos se deshabilitarán y las filas nuevas con atributos se retirarán. Se conservará o creará una variante sin atributos.',showCancelButton:true,confirmButtonText:'Continuar',cancelButtonText:'Cancelar'});
            if (!choice.isConfirmed) return;
            rows=rows.filter(r=>r.pva_id || !r.valores.length).map(r=>({...r,pva_estado:r.valores.length?0:1}));
            if (!rows.some(r=>!r.valores.length)) rows.push(newRow([]));
            selectors=[]; renderSelectors(); renderRows();
        } finally { busy=false; }
    });
    $('#catalogoForm').attr('novalidate',true).on('submit',async e=> {
        e.preventDefault(); if (busy || !C.validar(e.target)) return;
        if (!rows.length) return AvisoValidacionIFact('Agrega al menos una variante.');
        const data={pro_id:productoId,version,variantes:rows.map(r=>({pva_id:Number(r.pva_id),pva_sku:r.pva_sku.trim(),pva_codigo_barras:(r.pva_codigo_barras || '').trim(),pva_precio:r.pva_precio,pva_estado:r.pva_estado,valores:r.valores}))};
        ['pro_nombre','cat_id','und_med','pro_prec_venta','pro_estado'].forEach(key=>data[key]=$('#'+key).val());
        busy=true; $('#btn_guardar').prop('disabled',true);
        try {
            if (!await ConfirmarGuardadoIFact(productoId?'actualizar':'registrar','el producto y sus variantes')) return;
            const r=await C.api('producto','guardar',{datos:JSON.stringify(data)});
            if(eliminarProductoImagen)await imagenApi('eliminar_imagen_producto',r.data);
            if(productoImagenFile)await imagenApi('subir_imagen_producto',r.data,productoImagenFile);
            if(productoImagenPreview?.startsWith('blob:'))URL.revokeObjectURL(productoImagenPreview);
            $('#reg_edi').modal('hide'); tabla.ajax.reload(null,false); await AvisoIFact('Producto, tallas y foto guardados correctamente.');
        } catch(err) { C.error(err); }
        finally { busy=false; $('#btn_guardar').prop('disabled',false); }
    });
    $('#tablaCatalogo tbody').on('click keydown','tr.catalogo-producto-fila',function (event) {
        if (event.type==='keydown' && event.key!=='Enter' && event.key!==' ') return;
        if ($(event.target).closest('button,a,input,select,textarea,label').length) return;
        event.preventDefault();
        open(Number(this.dataset.productoId));
    });
    $('#tablaCatalogo').on('click','button[data-accion]',async function () {
        const id=Number(this.dataset.id), action=this.dataset.accion;
        if (action==='editar') return open(id);
        if (action!=='eliminar' || busy) return;
        try {
            const p=(await C.api('producto','obtener',{id})).data;
            return C.eliminar('producto',id,p.pro_nombre,()=>tabla.ajax.reload(null,false));
        } catch(e) { C.error(e); }
    });
});
