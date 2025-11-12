export const PlannerState = {
  mode: 'PLANNING', // 'PLANNING' | 'ASSIGNMENTS'
  filters: { projectId: null, groupId: null, state: '' },
  currentSlot: null,
  selection: new Set(),
  setMode(v){ this.mode = v; document.dispatchEvent(new CustomEvent('planner:mode', {detail:v})); },
  setFilters(f){ this.filters = {...this.filters, ...f}; document.dispatchEvent(new Event('planner:filters')); },
  setCurrentSlot(s){ this.currentSlot = s; document.dispatchEvent(new Event('planner:slot')); }
};

