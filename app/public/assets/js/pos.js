// /app/public/assets/js/pos.js
(function(){
  const $ = s=>document.querySelector(s);
  const $$ = s=>document.querySelectorAll(s);

  const items = []; // {product_id,name,qty,unit_price,discount_pct}
  const pays  = []; // {payment_method_id,amount,ref}

  // Cargar métodos de pago
  async function loadPaymentMethods(){
    try{
      const res = await fetch('/360/app/api/payments/list_methods.php').then(r=>r.ok?r.json():{ok:false});
      if(res && res.ok && Array.isArray(res.data)){
        const sel = $('#payment_method_id'); sel.innerHTML='';
        res.data.forEach(pm=>{
          const o = document.createElement('option');
          o.value = pm.id; o.textContent = pm.name; sel.appendChild(o);
        });
      }
    }catch(e){ /* silencioso */ }
  }
  loadPaymentMethods();

  // Simulación de obtención de producto por barcode o búsqueda (reemplazar con tu API)
  async function fetchProduct({barcode,name}){
    // TODO: reemplazar con /api/products/find.php?barcode=... o ?q=...
    // Por ahora devuelve un dummy para no frenar flujo
    return { id: 999, name: (name||('Código '+barcode)), price: 100.00 };
  }

  function render(){
    const tb = $('#items tbody'); tb.innerHTML='';
    let subtotal=0;
    items.forEach((it,idx)=>{
      const line = +(it.qty*it.unit_price*(1-(it.discount_pct||0)/100)).toFixed(2);
      subtotal += line;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${it.name}</td>
        <td class="right"><input type="number" step="0.01" value="${it.qty}" data-i="${idx}" class="i-qty" style="max-width:90px"></td>
        <td class="right"><input type="number" step="0.01" value="${it.unit_price}" data-i="${idx}" class="i-price" style="max-width:110px"></td>
        <td class="right"><input type="number" step="0.1"  value="${it.discount_pct||0}" data-i="${idx}" class="i-disc" style="max-width:90px"></td>
        <td class="right">${line.toFixed(2)}</td>
        <td class="right"><button data-i="${idx}" class="btn outline i-del">✕</button></td>`;
      tb.appendChild(tr);
    });
    const discTotal = parseFloat($('#discount_total').value||'0')||0;
    const total = Math.max(0, +(subtotal - discTotal).toFixed(2));
    $('#t_subtotal').textContent = subtotal.toFixed(2);
    $('#t_total').textContent    = total.toFixed(2);

    // pagos
    const cont = $('#payments'); cont.innerHTML='';
    let paid=0; pays.forEach((p,idx)=>{
      paid += +p.amount;
      const div = document.createElement('div');
      div.className='row';
      div.innerHTML = `<span>#${idx+1}</span>
        <span>PM:${p.payment_method_id}</span>
        <span>$${(+p.amount).toFixed(2)}</span>
        <span>${p.ref?('ref:'+p.ref):''}</span>
        <button class="btn outline p-del" data-i="${idx}">Eliminar</button>`;
      cont.appendChild(div);
    });
    $('#t_paid').textContent = paid.toFixed(2);

    // listeners items
    $$('.i-qty').forEach(inp=>inp.addEventListener('change',e=>{items[e.target.dataset.i].qty = +e.target.value||0; render();}));
    $$('.i-price').forEach(inp=>inp.addEventListener('change',e=>{items[e.target.dataset.i].unit_price = +e.target.value||0; render();}));
    $$('.i-disc').forEach(inp=>inp.addEventListener('change',e=>{items[e.target.dataset.i].discount_pct = +e.target.value||0; render();}));
    $$('.i-del').forEach(btn=>btn.addEventListener('click',e=>{items.splice(+e.target.dataset.i,1); render();}));
    $$('.p-del').forEach(btn=>btn.addEventListener('click',e=>{pays.splice(+e.target.dataset.i,1); render();}));
  }

  // Agregar producto dummy por ahora
  $('#btn_add').addEventListener('click', async ()=>{
    const bc = $('#barcode').value.trim();
    const q  = $('#search').value.trim();
    const p  = await fetchProduct({barcode: bc||null, name: q||null});
    if(!p) return;
    items.push({product_id:p.id, name:p.name, qty:1, unit_price:+p.price, discount_pct:0});
    $('#barcode').value=''; $('#search').value='';
    render();
  });

  $('#discount_total').addEventListener('change', render);

  // Pagos
  $('#btn_add_payment').addEventListener('click',()=>{
    const pm = +($('#payment_method_id').value||0);
    const am = +($('#payment_amount').value||0);
    const rf = $('#payment_ref').value.trim()||null;
    if(pm>0 && am>0){ pays.push({payment_method_id:pm, amount:am, ref:rf}); render(); }
  });

  // --- CÓDIGO ACTUALIZADO ---
  // Confirmar venta → API
  $('#btn_confirm').addEventListener('click', () => {
    if (items.length === 0) {
        Swal.fire('Error', 'No hay items en el carrito.', 'error');
        return;
    }
    
    const payload = {
        branch_id: +$('#branch_id').value,
        doc_mode: $('#doc_mode').value,
        cbte_letter: $('#cbte_letter').value,
        items: items.map(i => ({ product_id: i.product_id, qty: i.qty, unit_price: i.unit_price, discount_pct: i.discount_pct })),
        discount_total: +($('#discount_total').value || 0),
        payments: pays,
        // Asumo que tienes un input para el client_id, si no, debes añadirlo.
        // Si usas un buscador de clientes, deberías guardar el ID en un campo oculto.
        client_id: document.getElementById('customer-id') ? document.getElementById('customer-id').value : 1 // Cliente por defecto (Consumidor Final)
    };

    // Muestra un indicador de carga
    Swal.fire({
        title: 'Procesando Venta...',
        text: 'Por favor, espere.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // 1. Primero, crea la venta en tu sistema
    fetch('/360/app/api/sales/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            const saleId = data.sale_id;

            // 2. Si se debe facturar, llama a la API fiscal
            if (data.needs_arca) {
                Swal.update({ title: 'Venta registrada. Emitiendo factura...' });

                fetch('/360/app/api/fiscal/emit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sale_id: saleId })
                })
                .then(response => response.json())
                .then(fiscalData => {
                    if (fiscalData.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Factura Emitida!',
                            html: `La venta y la factura se generaron correctamente.<br><b>CAE: ${fiscalData.result.cae}</b>`,
                            confirmButtonText: 'Ver Comprobante'
                        }).then(() => {
                            // Redirigir o limpiar el formulario
                            items.length=0; pays.length=0; $('#discount_total').value='0'; render();
                            window.open(`/360/app/public/sales/view.php?id=${saleId}`, '_blank');
                        });
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Venta Guardada, pero no se pudo facturar',
                            text: `Error de ARCA: ${fiscalData.message || 'Error desconocido'}`,
                            confirmButtonText: 'Ver Venta (sin factura)'
                        }).then(() => {
                           window.location.href = `/360/app/public/sales/view.php?id=${saleId}`;
                        });
                    }
                })
                .catch(error => {
                    Swal.fire('Error de Conexión', 'No se pudo conectar con el servicio de facturación. La venta fue guardada.', 'error');
                });
            } else {
                // Si era un Ticket X o similar (no fiscal)
                Swal.fire({
                    icon: 'success',
                    title: 'Venta Registrada',
                    text: `Se generó el comprobante interno #${saleId}.`,
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    items.length=0; pays.length=0; $('#discount_total').value='0'; render();
                });
            }
        } else {
            Swal.fire('Error al Crear Venta', data.error || 'Ocurrió un error desconocido', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error de Red', 'No se pudo completar la venta. Revisa tu conexión.', 'error');
    });
  });
  // --- FIN CÓDIGO ACTUALIZADO ---


  // Atajos básicos
  $('#barcode').addEventListener('keydown',e=>{ if(e.key==='Enter') $('#btn_add').click(); });
  $('#search').addEventListener('keydown',e=>{ if(e.key==='Enter') $('#btn_add').click(); });

  // Primera render
  render();
})();