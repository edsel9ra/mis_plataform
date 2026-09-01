'use client';

import { useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Link2, LockKeyhole, Plus, Save, Trash2, Unplug } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { SystemicMap, SystemicMapEdge, SystemicMapNode } from '@/types/mentor-workspace';

const DEFAULT_NODE_COLOR = '#4f46e5';

export function SystemicMapEditor({
  value,
  onSave,
  isSaving = false,
}: {
  value?: SystemicMap | null;
  onSave: (map: SystemicMap) => void;
  isSaving?: boolean;
}) {
  const t = useTranslations();
  const canvasRef = useRef<HTMLDivElement>(null);
  const dragRef = useRef<string | null>(null);
  const [nodes, setNodes] = useState<SystemicMapNode[]>(() => normalizeNodes(value?.nodes));
  const [edges, setEdges] = useState<SystemicMapEdge[]>(() => normalizeEdges(value?.edges));
  const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
  const [selectedEdgeId, setSelectedEdgeId] = useState<string | null>(null);
  const [isConnecting, setIsConnecting] = useState(false);
  const [connectionSourceId, setConnectionSourceId] = useState<string | null>(null);

  useEffect(() => {
    if (!value) return;

    setNodes(normalizeNodes(value?.nodes));
    setEdges(normalizeEdges(value?.edges));
    setSelectedNodeId(null);
    setSelectedEdgeId(null);
    setConnectionSourceId(null);
  }, [value]);

  const selectedNode = nodes.find((node) => node.id === selectedNodeId) ?? null;
  const selectedEdge = edges.find((edge) => edge.id === selectedEdgeId) ?? null;

  function addNode() {
    const node: SystemicMapNode = {
      id: createId('node'),
      label: t('mentor_workspace.new_node'),
      x: 50 + ((nodes.length * 7) % 25) - 12,
      y: 30 + ((nodes.length * 11) % 35),
      color: DEFAULT_NODE_COLOR,
    };
    setNodes((current) => [...current, node]);
    setSelectedNodeId(node.id);
    setSelectedEdgeId(null);
  }

  function removeSelectedNode() {
    if (!selectedNodeId) return;

    setNodes((current) => current.filter((node) => node.id !== selectedNodeId));
    setEdges((current) => current.filter((edge) => edge.source !== selectedNodeId && edge.target !== selectedNodeId));
    setSelectedNodeId(null);
  }

  function handleNodeClick(nodeId: string) {
    if (!isConnecting) {
      setSelectedNodeId(nodeId);
      setSelectedEdgeId(null);
      return;
    }

    if (!connectionSourceId) {
      setConnectionSourceId(nodeId);
      return;
    }

    if (connectionSourceId === nodeId) return;
    const duplicate = edges.some((edge) => edge.source === connectionSourceId && edge.target === nodeId);
    if (!duplicate) {
      setEdges((current) => [...current, {
        id: createId('edge'),
        source: connectionSourceId,
        target: nodeId,
        label: '',
      }]);
    }
    setConnectionSourceId(null);
    setIsConnecting(false);
  }

  function startDragging(event: React.PointerEvent<HTMLButtonElement>, nodeId: string) {
    if (isConnecting) return;

    dragRef.current = nodeId;
    event.currentTarget.setPointerCapture(event.pointerId);
    setSelectedNodeId(nodeId);
    setSelectedEdgeId(null);
  }

  function moveNode(event: React.PointerEvent<HTMLButtonElement>) {
    const nodeId = dragRef.current;
    const rect = canvasRef.current?.getBoundingClientRect();
    if (!nodeId || !rect) return;

    const x = Math.max(7, Math.min(93, ((event.clientX - rect.left) / rect.width) * 100));
    const y = Math.max(8, Math.min(92, ((event.clientY - rect.top) / rect.height) * 100));
    setNodes((current) => current.map((node) => node.id === nodeId ? { ...node, x, y } : node));
  }

  function stopDragging() {
    dragRef.current = null;
  }

  function updateNodeLabel(label: string) {
    if (!selectedNodeId) return;
    setNodes((current) => current.map((node) => node.id === selectedNodeId ? { ...node, label } : node));
  }

  function removeSelectedEdge() {
    if (!selectedEdgeId) return;
    setEdges((current) => current.filter((edge) => edge.id !== selectedEdgeId));
    setSelectedEdgeId(null);
  }

  function saveMap() {
    onSave({ nodes, edges });
  }

  return (
    <Card className="overflow-hidden border-slate-200 p-0">
      <div className="flex flex-col justify-between gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center">
        <div className="flex items-start gap-3">
          <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
            <LockKeyhole className="h-5 w-5" aria-hidden="true" />
          </span>
          <div>
            <h3 className="font-semibold text-slate-950">{t('mentor_workspace.systemic_map_title')}</h3>
            <p className="mt-1 text-xs leading-5 text-slate-500">{t('mentor_workspace.systemic_map_private')}</p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" size="sm" variant="outline" onClick={addNode}>
            <Plus className="h-4 w-4" aria-hidden="true" /> {t('mentor_workspace.add_node')}
          </Button>
          <Button
            type="button"
            size="sm"
            variant={isConnecting ? 'secondary' : 'outline'}
            onClick={() => {
              setIsConnecting((current) => !current);
              setConnectionSourceId(null);
            }}
          >
            <Link2 className="h-4 w-4" aria-hidden="true" /> {t(isConnecting ? 'mentor_workspace.cancel_connect' : 'mentor_workspace.connect_nodes')}
          </Button>
          <Button type="button" size="sm" loading={isSaving} onClick={saveMap}>
            <Save className="h-4 w-4" aria-hidden="true" /> {t('common.save')}
          </Button>
        </div>
      </div>

      {isConnecting && (
        <p className="border-b border-primary-100 bg-primary-50 px-5 py-3 text-sm text-primary-800">
          {connectionSourceId ? t('mentor_workspace.connect_choose_target') : t('mentor_workspace.connect_choose_source')}
        </p>
      )}

      <div className="grid gap-0 lg:grid-cols-[minmax(0,1fr)_250px]">
        <div ref={canvasRef} className="relative aspect-[4/3] min-h-[330px] overflow-hidden bg-slate-50 p-3 sm:min-h-[420px] sm:p-5">
          <svg className="absolute inset-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-label={t('mentor_workspace.systemic_map_canvas')} role="img">
            <defs>
              <marker id="systemic-map-arrow" markerWidth="5" markerHeight="5" refX="4" refY="2.5" orient="auto" markerUnits="strokeWidth">
                <path d="M0,0 L5,2.5 L0,5 z" fill="#94a3b8" />
              </marker>
            </defs>
            {edges.map((edge) => {
              const source = nodes.find((node) => node.id === edge.source);
              const target = nodes.find((node) => node.id === edge.target);
              if (!source || !target) return null;
              const isSelected = selectedEdgeId === edge.id;
              return (
                <line
                  key={edge.id}
                  x1={source.x}
                  y1={source.y}
                  x2={target.x}
                  y2={target.y}
                  stroke={isSelected ? '#4f46e5' : '#94a3b8'}
                  strokeWidth={isSelected ? 1.2 : 0.8}
                   strokeDasharray={isSelected ? '0' : '2 1'}
                   markerEnd="url(#systemic-map-arrow)"
                   className="cursor-pointer transition-colors"
                   role="button"
                   tabIndex={0}
                   aria-label={t('mentor_workspace.select_edge', { label: edge.label || edge.id })}
                   onClick={() => {
                     setSelectedEdgeId(edge.id);
                     setSelectedNodeId(null);
                   }}
                   onKeyDown={(event) => {
                     if (event.key !== 'Enter' && event.key !== ' ') return;
                     event.preventDefault();
                     setSelectedEdgeId(edge.id);
                     setSelectedNodeId(null);
                   }}
                />
              );
            })}
          </svg>

          {nodes.length === 0 && (
            <div className="absolute inset-0 flex items-center justify-center p-8 text-center">
              <div>
                <Link2 className="mx-auto h-8 w-8 text-slate-300" aria-hidden="true" />
                <p className="mt-3 text-sm font-medium text-slate-600">{t('mentor_workspace.map_empty_title')}</p>
                <p className="mt-1 text-xs text-slate-500">{t('mentor_workspace.map_empty_description')}</p>
              </div>
            </div>
          )}

          {nodes.map((node) => (
            <button
              key={node.id}
              type="button"
              onClick={() => handleNodeClick(node.id)}
              onPointerDown={(event) => startDragging(event, node.id)}
              onPointerMove={moveNode}
              onPointerUp={stopDragging}
              onPointerCancel={stopDragging}
               className={cn(
                'absolute max-w-[38%] -translate-x-1/2 -translate-y-1/2 cursor-grab touch-none rounded-xl border-2 bg-white px-3 py-2 text-left text-xs font-semibold text-slate-800 shadow-md transition hover:shadow-lg active:cursor-grabbing sm:max-w-[30%] sm:px-4 sm:py-3 sm:text-sm',
                selectedNodeId === node.id ? 'border-primary-500 ring-4 ring-primary-100' : 'border-white',
                connectionSourceId === node.id && 'border-amber-500 ring-4 ring-amber-100',
              )}
              style={{ left: `${node.x}%`, top: `${node.y}%`, borderTopColor: node.color || DEFAULT_NODE_COLOR }}
              aria-label={t('mentor_workspace.select_node', { label: node.label })}
            >
              <span className="block truncate">{node.label}</span>
            </button>
          ))}
        </div>

        <aside className="border-t border-slate-100 bg-white p-5 lg:border-l lg:border-t-0">
          {selectedNode ? (
            <div className="space-y-4">
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.15em] text-primary-700">{t('mentor_workspace.selected_node')}</p>
                <h4 className="mt-1 font-semibold text-slate-950">{t('mentor_workspace.edit_node')}</h4>
              </div>
              <Input label={t('mentor_workspace.node_label')} value={selectedNode.label} onChange={(event) => updateNodeLabel(event.target.value)} />
              <Button type="button" variant="danger" size="sm" onClick={removeSelectedNode}>
                <Trash2 className="h-4 w-4" aria-hidden="true" /> {t('mentor_workspace.remove_node')}
              </Button>
              <p className="text-xs leading-5 text-slate-500">{t('mentor_workspace.drag_node_hint')}</p>
            </div>
          ) : selectedEdge ? (
            <div className="space-y-4">
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.15em] text-primary-700">{t('mentor_workspace.selected_edge')}</p>
                <h4 className="mt-1 font-semibold text-slate-950">{t('mentor_workspace.edit_edge')}</h4>
              </div>
              <Input
                label={t('mentor_workspace.edge_label')}
                value={selectedEdge.label ?? ''}
                onChange={(event) => setEdges((current) => current.map((edge) => edge.id === selectedEdge.id ? { ...edge, label: event.target.value } : edge))}
              />
              <Button type="button" variant="danger" size="sm" onClick={removeSelectedEdge}>
                <Unplug className="h-4 w-4" aria-hidden="true" /> {t('mentor_workspace.remove_edge')}
              </Button>
            </div>
          ) : (
            <div className="text-sm leading-6 text-slate-500">
              <p className="font-medium text-slate-700">{t('mentor_workspace.map_editor_hint_title')}</p>
              <p className="mt-2">{t('mentor_workspace.map_editor_hint')}</p>
            </div>
          )}
        </aside>
      </div>
    </Card>
  );
}

function normalizeNodes(nodes: SystemicMapNode[] | undefined): SystemicMapNode[] {
  return (nodes ?? []).map((node, index) => ({
    id: node.id || createId(`node-${index}`),
    label: node.label || 'Node',
    x: clamp(node.x, 7, 93, 20 + index * 15),
    y: clamp(node.y, 8, 92, 25 + index * 12),
    color: node.color || DEFAULT_NODE_COLOR,
    kind: node.kind,
  }));
}

function normalizeEdges(edges: SystemicMapEdge[] | undefined): SystemicMapEdge[] {
  return (edges ?? []).map((edge, index) => ({
    id: edge.id || createId(`edge-${index}`),
    source: edge.source,
    target: edge.target,
    label: edge.label || '',
  }));
}

function clamp(value: number, min: number, max: number, fallback: number): number {
  return Number.isFinite(value) ? Math.max(min, Math.min(max, value)) : fallback;
}

function createId(prefix: string): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) return `${prefix}-${crypto.randomUUID()}`;
  return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}
