import { PlannerAPI } from './planner-api.js';
import { PlannerState } from './planner-state.js';
import { initDrawer } from './planner-drawer.js';

document.addEventListener('DOMContentLoaded', ()=>{
  initDrawer();

  const calendarEl = document.getElementById('calendar');
  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'timeGridWeek',
    selectable: true,
    eventClick: info=> PlannerState.setCurrentSlot(info.event.extendedProps),
    select: async (sel)=>{
      const payload = {
        start: sel.startStr, end: sel.endStr,
        projectId: PlannerState.filters.projectId, groupId: PlannerState.filters.groupId,
        required_qty: 1
      };
      await PlannerAPI.createSlot(payload);
      calendar.refetchEvents();
    },
    events: async (info, success)=>{
      const res = await PlannerAPI.loadEvents(info);
      const events = (res.data || []).map(e=>({
        id: e.id,
        title: PlannerState.mode==='PLANNING'
          ? `${e.covered ?? 0}/${e.required ?? e.required_qty ?? 0}`
          : (e.assignees?.join(', ') || 'Sin asignar'),
        start: e.start, end: e.end,
        backgroundColor: colorByState(e.state),
        borderColor: colorByState(e.state),
        extendedProps: e
      }));
      success(events);
    }
  });
  calendar.render();

  // Mode toggle
  document.getElementById('mode-toggle').addEventListener('change', e=>{
    PlannerState.setMode(e.target.checked ? 'ASSIGNMENTS' : 'PLANNING');
  });

  // Filters (hook up your selects)
  document.getElementById('filter-state').addEventListener('change', (e)=>{
    PlannerState.setFilters({ state: e.target.value || '' });
  });

  document.addEventListener('planner:filters', ()=> calendar.refetchEvents());
  document.addEventListener('planner:mode', ()=> calendar.refetchEvents());
  document.addEventListener('planner:refresh', ()=> calendar.refetchEvents());
});

function colorByState(state){
  const map = {
    UNCOVERED: '#e74c3c',
    PROPOSED: '#f39c12',
    CONFIRMED: '#3498db',
    ASSIGNED: '#2c3e50',
    IN_PROGRESS: '#27ae60',
    CLOSED: '#95a5a6'
  };
  return map[state] || '';
}

