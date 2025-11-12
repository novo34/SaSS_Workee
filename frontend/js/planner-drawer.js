import { PlannerState } from './planner-state.js';
import { PlannerAPI } from './planner-api.js';

const drawer = document.getElementById('drawer');
const content = document.getElementById('drawer-content');

function openDrawer(){ drawer.classList.remove('hidden'); }
function setTab(name){
  [...drawer.querySelectorAll('.drawer-tabs button')].forEach(b=>b.classList.toggle('active', b.dataset.tab===name));
  render(name);
}

function render(tab){
  const slot = PlannerState.currentSlot;
  if(!slot){ content.innerHTML = '<p>Selecciona un slot/evento.</p>'; return; }

  if(tab==='summary'){
    content.innerHTML = `
      <h3>Resumen</h3>
      <p><b>Proyecto:</b> ${slot.projectName ?? ''}</p>
      <p><b>Grupo:</b> ${slot.groupName ?? ''}</p>
      <p><b>Horario:</b> ${slot.start} - ${slot.end}</p>
      <p><b>Estado:</b> ${slot.state}</p>
      <div class="actions">
        <button id="btn-create-request">Solicitar personal</button>
        ${slot.state==='PROPOSED' ? '<button id="btn-view-proposals">Ver propuestas</button>' : ''}
        ${slot.state==='CONFIRMED' || slot.state==='PROPOSED' ? '<button id="btn-open-assign">Asignar</button>' : ''}
        ${slot.state==='ASSIGNED' ? '<button id="btn-substitute">Sustituir</button>' : '' }
        <button id="btn-incident">Incidencia</button>
      </div>`;
    content.querySelector('#btn-create-request')?.addEventListener('click', ()=>setTab('requests'));
    content.querySelector('#btn-view-proposals')?.addEventListener('click', ()=>setTab('proposals'));
    content.querySelector('#btn-open-assign')?.addEventListener('click', ()=>setTab('assignments'));
    content.querySelector('#btn-substitute')?.addEventListener('click', ()=>setTab('assignments'));
    content.querySelector('#btn-incident')?.addEventListener('click', ()=>setTab('incidents'));
  }

  if(tab==='requests'){
    content.innerHTML = `
      <h3>Solicitar personal</h3>
      <form id="req-form">
        <label>Cantidad</label><input name="qty" type="number" min="1" required/>
        <label>Skills</label><input name="skills" placeholder="ej. limpieza, lider"/>
        <label>Diferido/urgente</label>
        <select name="priority"><option>normal</option><option>urgent</option></select>
        <label>Notas</label><textarea name="notes"></textarea>
        <button type="submit">Enviar solicitud</button>
      </form>`;
    content.querySelector('#req-form').addEventListener('submit', async (e)=>{
      e.preventDefault();
      const f = new FormData(e.target);
      await PlannerAPI.createRequest({
        slotId: slot.id, qty: Number(f.get('qty')),
        skills: String(f.get('skills')||''), priority: String(f.get('priority')),
        notes: String(f.get('notes')||'')
      });
      setTab('summary'); document.dispatchEvent(new Event('planner:refresh'));
    });
  }

  if(tab==='proposals'){
    content.innerHTML = `<h3>Propuestas</h3><div id="prop-list">Cargando...</div>`;
    (async ()=>{
      const res = await PlannerAPI.loadProposals(slot.requestId);
      const list = res.data || [];
      content.querySelector('#prop-list').innerHTML = list.map(p=>`
        <div class="card">
          <div>${p.personName} — ${p.skillSummary}</div>
          <button data-id="${p.id}" class="btn-confirm">Confirmar</button>
        </div>`).join('') || '<p>Sin propuestas</p>';
      content.querySelectorAll('.btn-confirm').forEach(b=>{
        b.addEventListener('click', async ()=>{
          await PlannerAPI.confirmProposal(b.dataset.id);
          setTab('summary'); document.dispatchEvent(new Event('planner:refresh'));
        });
      });
    })();
  }

  if(tab==='assignments'){
    content.innerHTML = `
      <h3>Asignaciones</h3>
      <p>Asigna confirmados al slot o realiza sustituciones rápidas.</p>
      <button id="btn-assign">Asignar confirmados</button>
      <button id="btn-substitute2">Sustituir</button>`;
    content.querySelector('#btn-assign').addEventListener('click', async ()=>{
      await PlannerAPI.assign({ slotId: slot.id, people: slot.confirmedIds || [] });
      setTab('summary'); document.dispatchEvent(new Event('planner:refresh'));
    });
    content.querySelector('#btn-substitute2').addEventListener('click', async ()=>{
      await PlannerAPI.substitute({ slotId: slot.id, oldPersonId: slot.assignedId, newPersonId: null });
      setTab('summary'); document.dispatchEvent(new Event('planner:refresh'));
    });
  }

  if(tab==='incidents'){
    content.innerHTML = `
      <h3>Incidencia</h3>
      <form id="inc-form">
        <label>Tipo</label>
        <select name="type">
          <option value="ABSENT">Ausencia</option>
          <option value="LATE">Retraso</option>
          <option value="OTHER">Otra</option>
        </select>
        <label>Detalle</label><textarea name="detail"></textarea>
        <button type="submit">Reportar</button>
      </form>`;
    content.querySelector('#inc-form').addEventListener('submit', async (e)=>{
      e.preventDefault();
      const f = new FormData(e.target);
      await PlannerAPI.incident({ slotId: slot.id, type:f.get('type'), detail:f.get('detail') });
      setTab('summary'); document.dispatchEvent(new Event('planner:refresh'));
    });
  }
}

export function initDrawer(){
  drawer.querySelectorAll('.drawer-tabs button').forEach(b=>{
    b.addEventListener('click', ()=> setTab(b.dataset.tab));
  });
  document.addEventListener('planner:slot', ()=>{ openDrawer(); setTab('summary'); });
}

