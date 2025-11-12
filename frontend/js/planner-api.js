import { api } from './api-client.js';
import { PlannerState } from './planner-state.js';

export const PlannerAPI = {
  async loadEvents(view){
    const { projectId, groupId, state } = PlannerState.filters;
    const mode = PlannerState.mode;
    return api.get('/api/planner.php', {
      path:'events', projectId, groupId, state,
      from:view.startStr, to:view.endStr, mode
    });
  },
  createSlot(payload){ return api.post('/api/planner.php?path=slot', payload); },
  createRequest(payload){ return api.post('/api/requests.php?action=create', payload); },     // si ya existe
  loadProposals(requestId){ return api.get(`/api/requests.php?action=proposals&requestId=${requestId}`); },
  confirmProposal(id){ return api.post(`/api/proposals.php?action=confirm`, { id }); },
  assign(payload){ return api.post('/api/assignments.php?action=assign', payload); },        // reusa tu endpoint si existe
  substitute(payload){ return api.post('/api/shifts.php?action=substitute', payload); },
  incident(payload){ return api.post('/api/incidences.php?action=create', payload); }
};

