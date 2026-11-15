<template>
  <div class="space-y-6">
    <!-- Top Header & Section Title -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/40 pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
            <Network class="w-3.5 h-3.5" />
            Phase 6 Production Engine
          </span>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-mono font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            10-Dimension Verification
          </span>
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
          Intelligence Research & Verification Engine
        </h1>
        <p class="text-xs text-muted-foreground mt-0.5">
          Entity resolution, multi-tier relationship graphs, 10-dimension fact verification, historical event timelines, and grounded AI deep research.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="openNewEntityModal"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-secondary text-secondary-foreground hover:bg-secondary/80 border border-border/60 transition-colors"
        >
          <Plus class="w-3.5 h-3.5" />
          Add Entity
        </button>
        <button
          @click="openNewResearchModal"
          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-md text-xs font-medium bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm transition-colors"
        >
          <Sparkles class="w-3.5 h-3.5" />
          New Deep Research
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-border/60 overflow-x-auto no-scrollbar">
      <nav class="flex space-x-1 min-w-max pb-px">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="switchTab(tab.id)"
          :class="[
            'px-3.5 py-2 text-xs font-medium rounded-t-md transition-colors flex items-center gap-2 border-b-2',
            activeTab === tab.id
              ? 'border-primary text-primary bg-primary/5'
              : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border/80'
          ]"
        >
          <component :is="tab.icon" class="w-3.5 h-3.5" />
          <span>{{ tab.label }}</span>
          <span v-if="tab.badge" class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-primary/20 text-primary">
            {{ tab.badge }}
          </span>
        </button>
      </nav>
    </div>

    <!-- Tab 1: Overview Dashboard -->
    <div v-if="activeTab === 'overview'" class="space-y-6">
      <!-- Telemetry Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5">
        <div class="p-4 rounded-lg bg-card border border-border/50 flex flex-col justify-between">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-xs font-medium">Total Entities</span>
            <Users class="w-4 h-4 text-primary" />
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-2xl font-bold text-foreground font-mono">{{ overviewData?.metrics?.total_entities ?? 0 }}</span>
            <span class="text-[11px] text-emerald-400 font-medium">
              {{ overviewData?.metrics?.verified_entities ?? 0 }} verified
            </span>
          </div>
        </div>

        <div class="p-4 rounded-lg bg-card border border-border/50 flex flex-col justify-between">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-xs font-medium">Graph Relationships</span>
            <Network class="w-4 h-4 text-sky-400" />
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-2xl font-bold text-foreground font-mono">{{ overviewData?.metrics?.total_relationships ?? 0 }}</span>
            <span class="text-[11px] text-muted-foreground">Active edges</span>
          </div>
        </div>

        <div class="p-4 rounded-lg bg-card border border-border/50 flex flex-col justify-between">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-xs font-medium">Fact Claims</span>
            <ShieldCheck class="w-4 h-4 text-emerald-400" />
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-2xl font-bold text-foreground font-mono">{{ overviewData?.metrics?.verified_claims ?? 0 }}</span>
            <span class="text-[11px] text-amber-400 font-medium" v-if="overviewData?.metrics?.disputed_claims">
              {{ overviewData?.metrics?.disputed_claims }} disputed
            </span>
          </div>
        </div>

        <div class="p-4 rounded-lg bg-card border border-border/50 flex flex-col justify-between">
          <div class="flex items-center justify-between text-muted-foreground">
            <span class="text-xs font-medium">Active Trends</span>
            <TrendingUp class="w-4 h-4 text-amber-400" />
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-2xl font-bold text-foreground font-mono">{{ overviewData?.metrics?.active_trends ?? 0 }}</span>
            <span class="text-[11px] text-muted-foreground">Signals tracked</span>
          </div>
        </div>
      </div>

      <!-- Quick Launch & Recent Reports Section -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Deep Research Reports -->
        <div class="lg:col-span-2 rounded-lg bg-card border border-border/50 p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-foreground flex items-center gap-2">
              <FileText class="w-4 h-4 text-primary" />
              Latest Deep Research Reports
            </h3>
            <button @click="switchTab('reports')" class="text-xs text-primary hover:underline">
              View all
            </button>
          </div>

          <div v-if="overviewData?.recent_reports?.length" class="space-y-2.5">
            <div
              v-for="rep in overviewData.recent_reports"
              :key="rep.id"
              class="p-3 rounded-md bg-muted/30 border border-border/40 hover:bg-muted/50 transition-colors flex items-center justify-between"
            >
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-semibold text-foreground">{{ rep.title }}</span>
                  <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-mono font-medium bg-primary/10 text-primary border border-primary/20">
                    {{ rep.editorial_status }}
                  </span>
                </div>
                <p class="text-[11px] text-muted-foreground line-clamp-1">{{ rep.executive_summary }}</p>
              </div>
              <button
                @click="inspectReport(rep.id)"
                class="px-2.5 py-1 text-xs font-medium text-foreground bg-secondary hover:bg-secondary/80 rounded border border-border/50"
              >
                Inspect
              </button>
            </div>
          </div>
          <div v-else class="text-center py-8 text-xs text-muted-foreground">
            No research reports synthesized yet. Launch one from the Deep Research Workspace.
          </div>
        </div>

        <!-- Emerging Trend Radar -->
        <div class="rounded-lg bg-card border border-border/50 p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-foreground flex items-center gap-2">
              <Compass class="w-4 h-4 text-amber-400" />
              Emerging Signals Radar
            </h3>
            <button @click="triggerTrendScan" class="text-xs text-primary hover:underline">
              Scan
            </button>
          </div>

          <div v-if="overviewData?.top_trends?.length" class="space-y-2">
            <div
              v-for="tr in overviewData.top_trends"
              :key="tr.id"
              class="p-2.5 rounded bg-muted/20 border border-border/40 flex items-center justify-between"
            >
              <div>
                <div class="text-xs font-medium text-foreground">{{ tr.name }}</div>
                <div class="text-[10px] text-muted-foreground font-mono">
                  {{ tr.story_count }} stories · {{ tr.independent_sources_count }} sources
                </div>
              </div>
              <span class="px-2 py-0.5 rounded text-xs font-bold font-mono bg-amber-500/10 text-amber-400 border border-amber-500/20">
                {{ tr.composite_score }}
              </span>
            </div>
          </div>
          <div v-else class="text-center py-6 text-xs text-muted-foreground">
            No active signals. Click Scan to evaluate trend velocity.
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 2: Entity Management & Resolution -->
    <div v-else-if="activeTab === 'entities'" class="space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-card p-3 rounded-lg border border-border/50">
        <div class="flex items-center gap-2 flex-1">
          <Search class="w-4 h-4 text-muted-foreground" />
          <input
            v-model="entitySearch"
            @input="debouncedFetchEntities"
            type="text"
            placeholder="Search entities, legal names, aliases..."
            class="bg-transparent text-xs text-foreground placeholder:text-muted-foreground focus:outline-none w-full"
          />
        </div>
        <div class="flex items-center gap-2">
          <select
            v-model="entityTypeFilter"
            @change="fetchEntities"
            class="bg-muted text-xs text-foreground px-2.5 py-1.5 rounded border border-border/60"
          >
            <option value="">All Entity Types</option>
            <option value="company">Companies</option>
            <option value="executive">Executives</option>
            <option value="product">Products</option>
            <option value="technology">Technologies</option>
            <option value="industry">Industries</option>
            <option value="institution">Institutions</option>
            <option value="investor">Investors</option>
            <option value="government">Government</option>
          </select>
          <select
            v-model="entityStatusFilter"
            @change="fetchEntities"
            class="bg-muted text-xs text-foreground px-2.5 py-1.5 rounded border border-border/60"
          >
            <option value="">All Statuses</option>
            <option value="verified">Verified</option>
            <option value="unverified">Unverified</option>
            <option value="disputed">Disputed</option>
            <option value="archived">Archived</option>
          </select>
          <button
            @click="openReviewQueueModal"
            class="px-3 py-1.5 text-xs font-medium rounded bg-secondary text-secondary-foreground hover:bg-secondary/80 border border-border/60 flex items-center gap-1.5"
          >
            <Inbox class="w-3.5 h-3.5" />
            Resolution Queue
          </button>
        </div>
      </div>

      <!-- Entities Table -->
      <div class="rounded-lg bg-card border border-border/50 overflow-hidden">
        <table class="w-full text-left text-xs">
          <thead class="bg-muted/40 border-b border-border/50 text-muted-foreground uppercase text-[10px]">
            <tr>
              <th class="py-2.5 px-4 font-semibold">Entity Name</th>
              <th class="py-2.5 px-3 font-semibold">Type</th>
              <th class="py-2.5 px-3 font-semibold">Status</th>
              <th class="py-2.5 px-3 font-semibold">Confidence</th>
              <th class="py-2.5 px-3 font-semibold">Aliases</th>
              <th class="py-2.5 px-3 font-semibold">Graph Edges</th>
              <th class="py-2.5 px-4 text-right font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border/40">
            <tr v-for="ent in entitiesList" :key="ent.id" class="hover:bg-muted/30 transition-colors">
              <td class="py-3 px-4">
                <div class="font-semibold text-foreground">{{ ent.canonical_name }}</div>
                <div class="text-[11px] text-muted-foreground line-clamp-1">{{ ent.legal_name || ent.description }}</div>
              </td>
              <td class="py-3 px-3">
                <span class="px-2 py-0.5 rounded text-[10px] uppercase font-mono font-semibold bg-muted text-foreground border border-border/40">
                  {{ ent.entity_type }}
                </span>
              </td>
              <td class="py-3 px-3">
                <span
                  :class="[
                    'px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase',
                    ent.verification_status === 'verified' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                    ent.verification_status === 'disputed' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' :
                    'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                  ]"
                >
                  {{ ent.verification_status }}
                </span>
              </td>
              <td class="py-3 px-3 font-mono font-medium">
                {{ Math.round((ent.confidence_score || 1.0) * 100) }}%
              </td>
              <td class="py-3 px-3 font-mono text-muted-foreground">
                {{ ent.aliases_count || 0 }} aliases
              </td>
              <td class="py-3 px-3 font-mono text-muted-foreground">
                {{ (ent.outgoing_relationships_count || 0) + (ent.incoming_relationships_count || 0) }} edges
              </td>
              <td class="py-3 px-4 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    v-if="ent.verification_status !== 'verified'"
                    @click="verifyEntity(ent.id)"
                    class="px-2 py-1 rounded text-[11px] bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/20"
                    title="Verify Entity"
                  >
                    Verify
                  </button>
                  <button
                    @click="openMergeModal(ent)"
                    class="px-2 py-1 rounded text-[11px] bg-secondary text-secondary-foreground hover:bg-secondary/80 border border-border/50"
                    title="Merge Into Other Entity"
                  >
                    Merge
                  </button>
                  <button
                    @click="openSplitModal(ent)"
                    class="px-2 py-1 rounded text-[11px] bg-secondary text-secondary-foreground hover:bg-secondary/80 border border-border/50"
                    title="Split Aliases"
                  >
                    Split
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!entitiesList.length" class="text-center py-10 text-xs text-muted-foreground">
          No intelligence entities match the current criteria.
        </div>
      </div>
    </div>

    <!-- Tab 3: Company Profiles -->
    <div v-else-if="activeTab === 'companies'" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="comp in companiesList"
          :key="comp.id"
          class="p-4 rounded-lg bg-card border border-border/50 space-y-3 flex flex-col justify-between"
        >
          <div>
            <div class="flex items-center justify-between">
              <span class="text-sm font-bold text-foreground">{{ comp.canonical_name }}</span>
              <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                {{ comp.verification_status }}
              </span>
            </div>
            <div class="text-[11px] text-muted-foreground mt-1">{{ comp.legal_name || comp.industry || 'Technology Corporation' }}</div>
            <p class="text-xs text-foreground/80 mt-2 line-clamp-2">{{ comp.description || 'Global technology enterprise profile and strategic analysis.' }}</p>
          </div>

          <div class="border-t border-border/40 pt-2 flex items-center justify-between text-[11px] text-muted-foreground font-mono">
            <span>{{ comp.products_count || 0 }} products</span>
            <span>{{ comp.competitors_count || 0 }} competitors</span>
            <button @click="inspectGraphForEntity(comp.id)" class="text-primary hover:underline">
              View Graph
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 4: Executive Intelligence -->
    <div v-else-if="activeTab === 'executives'" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="exec in executivesList"
          :key="exec.id"
          class="p-4 rounded-lg bg-card border border-border/50 space-y-3"
        >
          <div class="flex items-center justify-between">
            <span class="text-sm font-bold text-foreground">{{ exec.canonical_name }}</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-primary/10 text-primary border border-primary/20">
              Executive
            </span>
          </div>
          <p class="text-xs text-muted-foreground line-clamp-2">{{ exec.description || 'Documented executive career record.' }}</p>
          <div class="space-y-1.5 border-t border-border/40 pt-2">
            <div class="text-[10px] uppercase font-semibold text-muted-foreground">Documented Positions</div>
            <div v-for="rel in exec.outgoing_relationships" :key="rel.id" class="text-xs text-foreground flex items-center justify-between">
              <span>{{ rel.relationship_type.replace('_', ' ') }}: {{ rel.target_entity?.canonical_name }}</span>
              <span class="text-[10px] font-mono text-muted-foreground">{{ rel.verification_status }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 5: Product & Technology Intelligence -->
    <div v-else-if="activeTab === 'technology'" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="tech in technologyList"
          :key="tech.id"
          class="p-4 rounded-lg bg-card border border-border/50 space-y-3"
        >
          <div class="flex items-center justify-between">
            <span class="text-sm font-bold text-foreground">{{ tech.canonical_name }}</span>
            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-mono font-medium bg-sky-500/10 text-sky-400 border border-sky-500/20">
              {{ tech.entity_type }}
            </span>
          </div>
          <p class="text-xs text-muted-foreground line-clamp-2">{{ tech.description || 'Technological component or platform specifications.' }}</p>
          <div class="text-[11px] text-muted-foreground border-t border-border/40 pt-2 flex items-center justify-between">
            <span>Status: {{ tech.verification_status }}</span>
            <button @click="inspectGraphForEntity(tech.id)" class="text-primary hover:underline">
              Inspect Network
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 6: Interactive Relationship Graph -->
    <div v-else-if="activeTab === 'graph'" class="space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-card p-3 rounded-lg border border-border/50">
        <div class="flex items-center gap-3">
          <label class="text-xs font-semibold text-foreground">Root Entity:</label>
          <select
            v-model="graphRootId"
            @change="fetchGraph"
            class="bg-muted text-xs text-foreground px-2.5 py-1.5 rounded border border-border/60"
          >
            <option v-for="ent in entitiesList.slice(0, 30)" :key="ent.id" :value="ent.id">
              {{ ent.canonical_name }} ({{ ent.entity_type }})
            </option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="openNewRelationshipModal"
            class="px-3 py-1.5 text-xs font-medium rounded bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-1.5"
          >
            <Plus class="w-3.5 h-3.5" />
            Add Edge
          </button>
        </div>
      </div>

      <!-- Graph Canvas Visualizer Display -->
      <div class="rounded-lg bg-card border border-border/50 p-6 min-h-[420px] flex flex-col justify-between">
        <div class="flex items-center justify-between text-xs border-b border-border/40 pb-3">
          <span class="text-muted-foreground font-mono">
            Nodes: {{ graphData?.metadata?.total_nodes || 0 }} | Edges: {{ graphData?.metadata?.total_links || 0 }}
          </span>
          <div class="flex items-center gap-2 text-[11px]">
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Verified</span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-sky-400"></span> Single Source</span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-400"></span> AI Inferred</span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-400"></span> Disputed</span>
          </div>
        </div>

        <!-- Rendered Node Network Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 my-4">
          <div
            v-for="link in graphData?.links || []"
            :key="link.id"
            class="p-3 rounded-md bg-muted/20 border border-border/40 space-y-1.5"
          >
            <div class="flex items-center justify-between text-xs">
              <span class="font-semibold text-foreground">{{ getNodeName(link.source) }}</span>
              <span class="px-1.5 py-0.5 rounded text-[10px] font-mono uppercase bg-primary/10 text-primary">
                {{ link.type.replace('_', ' ') }}
              </span>
              <span class="font-semibold text-foreground">{{ getNodeName(link.target) }}</span>
            </div>
            <p v-if="link.evidence" class="text-[11px] text-muted-foreground line-clamp-1 italic">
              "{{ link.evidence }}"
            </p>
            <div class="flex items-center justify-between text-[10px] text-muted-foreground pt-1 border-t border-border/30">
              <span>Source: {{ link.source_name }}</span>
              <span :class="link.verification_status === 'verified' ? 'text-emerald-400' : 'text-amber-400'">
                {{ link.verification_status }}
              </span>
            </div>
          </div>
        </div>

        <div v-if="!graphData?.links?.length" class="text-center py-16 text-xs text-muted-foreground">
          No relationship edges connected to this root entity. Select another root or create a relationship.
        </div>
      </div>
    </div>

    <!-- Tab 7: Fact Verification Center -->
    <div v-else-if="activeTab === 'verification'" class="space-y-4">
      <div class="rounded-lg bg-card border border-border/50 divide-y divide-border/40">
        <div
          v-for="fact in factsList"
          :key="fact.id"
          class="p-4 space-y-3 hover:bg-muted/10 transition-colors"
        >
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase',
                  fact.verification_status === 'supported_multiple' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                  fact.verification_status === 'conflicting' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' :
                  'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                ]"
              >
                {{ fact.verification_status }}
              </span>
              <span class="text-xs font-semibold text-foreground">{{ fact.entity || 'General Intelligence' }}</span>
            </div>
            <div class="text-xs font-mono font-medium text-muted-foreground">
              Confidence: {{ Math.round((fact.confidence_score || 0.85) * 100) }}%
            </div>
          </div>

          <div class="text-xs text-foreground font-medium">"{{ fact.claim }}"</div>

          <!-- Evidence List -->
          <div v-if="fact.evidences?.length" class="space-y-1.5 pl-3 border-l-2 border-border/60">
            <div
              v-for="ev in fact.evidences"
              :key="ev.id"
              class="text-[11px] text-muted-foreground flex items-center justify-between gap-4"
            >
              <span>
                <strong :class="ev.stance === 'contradicts' ? 'text-rose-400' : 'text-emerald-400'">
                  [{{ ev.stance }}]
                </strong>
                {{ ev.evidence_text }}
              </span>
              <span class="font-mono text-[10px] whitespace-nowrap">{{ ev.source?.name }}</span>
            </div>
          </div>

          <div class="flex items-center justify-between pt-2 border-t border-border/30">
            <span class="text-[11px] text-muted-foreground">Original Source: {{ fact.source?.name }}</span>
            <div class="flex items-center gap-2">
              <button
                @click="openAttachEvidenceModal(fact)"
                class="px-2.5 py-1 text-xs rounded bg-secondary text-secondary-foreground hover:bg-secondary/80 border border-border/50"
              >
                Attach Evidence
              </button>
              <button
                @click="recordFactVerdict(fact.id, 'verified_by_editor')"
                class="px-2.5 py-1 text-xs rounded bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/20"
              >
                Verify Claim
              </button>
              <button
                @click="recordFactVerdict(fact.id, 'disputed')"
                class="px-2.5 py-1 text-xs rounded bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20"
              >
                Dispute Claim
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 8: Event Timeline -->
    <div v-else-if="activeTab === 'timeline'" class="space-y-4">
      <div class="flex justify-between items-center bg-card p-3 rounded-lg border border-border/50">
        <span class="text-xs text-muted-foreground">Chronological record of verified industry and company events</span>
        <button
          @click="openNewEventModal"
          class="px-3 py-1.5 text-xs font-medium rounded bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-1.5"
        >
          <Plus class="w-3.5 h-3.5" />
          Record Historical Event
        </button>
      </div>

      <div class="relative border-l border-border/60 ml-4 space-y-6 py-2">
        <div
          v-for="evt in timelineList"
          :key="evt.id"
          class="relative pl-6 space-y-1"
        >
          <div class="absolute -left-1.5 top-1.5 w-3 h-3 rounded-full bg-primary border-2 border-background"></div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-mono font-bold text-primary">{{ formatDate(evt.occurred_at) }}</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-muted text-foreground border border-border/40">
              {{ evt.event_category }}
            </span>
            <span class="text-xs font-bold text-foreground">{{ evt.title }}</span>
          </div>
          <p class="text-xs text-muted-foreground">{{ evt.summary }}</p>
          <div class="flex items-center gap-3 text-[11px] text-muted-foreground pt-1">
            <span>Reported: {{ formatDate(evt.reported_at) }}</span>
            <button @click="openCorrectDateModal(evt)" class="text-primary hover:underline">
              Correct Date
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 9: Trend Intelligence -->
    <div v-else-if="activeTab === 'trends'" class="space-y-4">
      <div class="flex justify-between items-center bg-card p-3 rounded-lg border border-border/50">
        <span class="text-xs text-muted-foreground">Transparent multi-factor trend score breakdown</span>
        <button
          @click="triggerTrendScan"
          class="px-3 py-1.5 text-xs font-medium rounded bg-secondary text-secondary-foreground hover:bg-secondary/80 border border-border/60 flex items-center gap-1.5"
        >
          <RefreshCw class="w-3.5 h-3.5" />
          Trigger On-Demand Scan
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="trend in trendsList"
          :key="trend.id"
          class="p-4 rounded-lg bg-card border border-border/50 space-y-3"
        >
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-bold text-foreground">{{ trend.name }}</div>
              <div class="text-[11px] text-muted-foreground font-mono">{{ trend.status }} · {{ trend.story_count }} stories</div>
            </div>
            <div class="text-right">
              <div class="text-2xl font-black font-mono text-amber-400">{{ trend.composite_score }}</div>
              <div class="text-[10px] text-muted-foreground uppercase font-mono">Composite</div>
            </div>
          </div>

          <!-- Formula Component Breakdown -->
          <div class="grid grid-cols-5 gap-1.5 pt-2 border-t border-border/40 text-center font-mono">
            <div class="p-1.5 rounded bg-muted/30">
              <div class="text-[10px] text-muted-foreground">Vol (25%)</div>
              <div class="text-xs font-bold text-foreground">{{ trend.volume_score }}</div>
            </div>
            <div class="p-1.5 rounded bg-muted/30">
              <div class="text-[10px] text-muted-foreground">Vel (30%)</div>
              <div class="text-xs font-bold text-foreground">{{ trend.growth_rate_score }}</div>
            </div>
            <div class="p-1.5 rounded bg-muted/30">
              <div class="text-[10px] text-muted-foreground">Div (20%)</div>
              <div class="text-xs font-bold text-foreground">{{ trend.source_diversity_score }}</div>
            </div>
            <div class="p-1.5 rounded bg-muted/30">
              <div class="text-[10px] text-muted-foreground">Cred (15%)</div>
              <div class="text-xs font-bold text-foreground">{{ trend.source_credibility_score }}</div>
            </div>
            <div class="p-1.5 rounded bg-muted/30">
              <div class="text-[10px] text-muted-foreground">Rel (10%)</div>
              <div class="text-xs font-bold text-foreground">{{ trend.entity_relevance_score }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 10: Deep Research Workspace -->
    <div v-else-if="activeTab === 'workspace'" class="space-y-6">
      <div class="p-5 rounded-lg bg-card border border-border/50 space-y-4">
        <h3 class="text-sm font-semibold text-foreground flex items-center gap-2">
          <Sparkles class="w-4 h-4 text-primary" />
          Initiate Deep Research Project
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-1">
            <label class="text-xs font-medium text-foreground">Project Title</label>
            <input
              v-model="newResearch.title"
              type="text"
              placeholder="e.g. Next-Gen Superconducting Qubit Architectures"
              class="w-full px-3 py-2 bg-muted text-xs text-foreground rounded border border-border/60"
            />
          </div>

          <div class="space-y-1">
            <label class="text-xs font-medium text-foreground">Output Format</label>
            <select
              v-model="newResearch.output_format"
              class="w-full px-3 py-2 bg-muted text-xs text-foreground rounded border border-border/60"
            >
              <option value="executive_summary">Executive Summary</option>
              <option value="company_profile">Company Intelligence Report</option>
              <option value="technology_assessment">Technology Assessment</option>
              <option value="industry_analysis">Industry Analysis</option>
              <option value="competitive_intelligence">Competitive Intelligence</option>
              <option value="product_comparison">Product Comparison</option>
              <option value="historical_event_analysis">Historical Event Analysis</option>
              <option value="trend_report">Trend Report</option>
            </select>
          </div>
        </div>

        <div class="space-y-1">
          <label class="text-xs font-medium text-foreground">Research Question & Strategic Scope</label>
          <textarea
            v-model="newResearch.research_question"
            rows="3"
            placeholder="Detailed research objective to evaluate against collected internal intelligence..."
            class="w-full px-3 py-2 bg-muted text-xs text-foreground rounded border border-border/60"
          ></textarea>
        </div>

        <div class="flex items-center justify-between pt-2">
          <span class="text-[11px] text-muted-foreground">Uses Phase 4 AI Router and grounds exclusively on verified database claims.</span>
          <button
            @click="launchDeepResearch"
            :disabled="isExecutingResearch"
            class="px-4 py-2 text-xs font-semibold rounded bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-2 disabled:opacity-50"
          >
            <Sparkles class="w-3.5 h-3.5" />
            {{ isExecutingResearch ? 'Executing Multi-Stage Synthesis...' : 'Launch Research Execution' }}
          </button>
        </div>
      </div>

      <!-- Execution Result / Active Project -->
      <div v-if="activeReport" class="p-5 rounded-lg bg-card border border-border/50 space-y-4">
        <div class="flex items-center justify-between border-b border-border/40 pb-3">
          <div>
            <h4 class="text-base font-bold text-foreground">{{ activeReport.title }}</h4>
            <div class="text-xs text-muted-foreground font-mono">Confidence: {{ Math.round((activeReport.confidence_score || 0.9) * 100) }}% | Rating: {{ activeReport.uncertainty_rating }}</div>
          </div>
          <span class="px-2 py-0.5 rounded text-xs font-mono uppercase bg-primary/10 text-primary border border-primary/20">
            {{ activeReport.editorial_status }}
          </span>
        </div>

        <div class="prose prose-sm dark:prose-invert max-w-none text-xs text-foreground/90 whitespace-pre-wrap font-sans">
          {{ activeReport.content_markdown }}
        </div>
      </div>
    </div>

    <!-- Tab 11: Research Reports Library -->
    <div v-else-if="activeTab === 'reports'" class="space-y-4">
      <div class="rounded-lg bg-card border border-border/50 divide-y divide-border/40">
        <div
          v-for="rep in reportsList"
          :key="rep.id"
          class="p-4 flex items-center justify-between hover:bg-muted/10 transition-colors"
        >
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-foreground">{{ rep.title }}</span>
              <span class="px-2 py-0.5 rounded text-[10px] uppercase font-mono font-medium bg-primary/10 text-primary border border-primary/20">
                {{ rep.editorial_status }}
              </span>
            </div>
            <div class="text-xs text-muted-foreground line-clamp-1">{{ rep.executive_summary }}</div>
            <div class="text-[10px] text-muted-foreground font-mono">
              {{ rep.citations_count || 0 }} grounded citations · {{ formatDate(rep.created_at) }}
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="inspectReport(rep.id)"
              class="px-3 py-1.5 text-xs font-medium rounded bg-secondary text-secondary-foreground hover:bg-secondary/80 border border-border/50"
            >
              Read Report
            </button>
            <button
              v-if="rep.editorial_status !== 'editorial_approved'"
              @click="approveReport(rep.id)"
              class="px-3 py-1.5 text-xs font-medium rounded bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/20"
            >
              Approve
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Network,
  Users,
  Building2,
  Cpu,
  ShieldCheck,
  TrendingUp,
  History,
  Sparkles,
  FileText,
  Search,
  Plus,
  Compass,
  Inbox,
  RefreshCw,
} from 'lucide-vue-next'
import { adminService } from '@/services/api/admin.service'

const route = useRoute()
const router = useRouter()

const activeTab = ref('overview')
const overviewData = ref<any>(null)
const entitiesList = ref<any[]>([])
const entitySearch = ref('')
const entityTypeFilter = ref('')
const entityStatusFilter = ref('')
const companiesList = ref<any[]>([])
const executivesList = ref<any[]>([])
const technologyList = ref<any[]>([])
const graphRootId = ref('')
const graphData = ref<any>(null)
const factsList = ref<any[]>([])
const timelineList = ref<any[]>([])
const trendsList = ref<any[]>([])
const reportsList = ref<any[]>([])
const activeReport = ref<any>(null)
const isExecutingResearch = ref(false)

const newResearch = ref({
  title: '',
  research_question: '',
  output_format: 'executive_summary',
})

interface TabItem {
  id: string
  label: string
  icon: any
  badge?: string | number
}

const tabs: TabItem[] = [
  { id: 'overview', label: 'Overview', icon: Compass },
  { id: 'entities', label: 'Entities', icon: Users },
  { id: 'companies', label: 'Companies', icon: Building2 },
  { id: 'executives', label: 'Executives', icon: Users },
  { id: 'technology', label: 'Tech & Products', icon: Cpu },
  { id: 'graph', label: 'Graph Explorer', icon: Network },
  { id: 'verification', label: 'Fact Verification', icon: ShieldCheck },
  { id: 'timeline', label: 'Event Timeline', icon: History },
  { id: 'trends', label: 'Trend Signals', icon: TrendingUp },
  { id: 'workspace', label: 'Deep Research', icon: Sparkles },
  { id: 'reports', label: 'Report Library', icon: FileText },
]

function switchTab(tabId: string) {
  activeTab.value = tabId
  router.push(`/admin/research/${tabId}`)
  loadTabContent(tabId)
}

async function loadTabContent(tabId: string) {
  switch (tabId) {
    case 'overview':
      await fetchOverview()
      break
    case 'entities':
      await fetchEntities()
      break
    case 'companies':
      await fetchCompanies()
      break
    case 'executives':
      await fetchExecutives()
      break
    case 'technology':
      await fetchTechnology()
      break
    case 'graph':
      await fetchGraph()
      break
    case 'verification':
      await fetchFacts()
      break
    case 'timeline':
      await fetchTimeline()
      break
    case 'trends':
      await fetchTrends()
      break
    case 'reports':
      await fetchReports()
      break
  }
}

async function fetchOverview() {
  try {
    overviewData.value = await adminService.getResearchOverview()
  } catch (err) {
    console.error('Failed to load research overview', err)
  }
}

async function fetchEntities() {
  try {
    const res = await adminService.getResearchEntities({
      search: entitySearch.value,
      type: entityTypeFilter.value,
      verification_status: entityStatusFilter.value,
    })
    entitiesList.value = res.data || []
    if (entitiesList.value.length && !graphRootId.value) {
      graphRootId.value = entitiesList.value[0].id
    }
  } catch (err) {
    console.error('Failed to load entities', err)
  }
}

let searchTimer: any = null
function debouncedFetchEntities() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    fetchEntities()
  }, 300)
}

async function fetchCompanies() {
  try {
    const res = await adminService.getResearchCompanies()
    companiesList.value = res.data || []
  } catch (err) {
    console.error('Failed to load companies', err)
  }
}

async function fetchExecutives() {
  try {
    const res = await adminService.getResearchExecutives()
    executivesList.value = res.data || []
  } catch (err) {
    console.error('Failed to load executives', err)
  }
}

async function fetchTechnology() {
  try {
    const res = await adminService.getResearchTechnology()
    technologyList.value = res.data || []
  } catch (err) {
    console.error('Failed to load tech', err)
  }
}

async function fetchGraph() {
  try {
    graphData.value = await adminService.getResearchGraph({
      root_id: graphRootId.value,
      depth: 2,
    })
  } catch (err) {
    console.error('Failed to load graph', err)
  }
}

function getNodeName(nodeId: string): string {
  const node = graphData.value?.nodes?.find((n: any) => n.id === nodeId)
  return node ? node.name : 'Entity'
}

function inspectGraphForEntity(entityId: string) {
  graphRootId.value = entityId
  switchTab('graph')
}

async function fetchFacts() {
  try {
    const res = await adminService.getResearchVerificationFacts()
    factsList.value = res.data || []
  } catch (err) {
    console.error('Failed to load facts', err)
  }
}

async function fetchTimeline() {
  try {
    timelineList.value = await adminService.getResearchTimeline()
  } catch (err) {
    console.error('Failed to load timeline', err)
  }
}

async function fetchTrends() {
  try {
    trendsList.value = await adminService.getResearchTrends()
  } catch (err) {
    console.error('Failed to load trends', err)
  }
}

async function triggerTrendScan() {
  try {
    await adminService.triggerResearchTrendScan()
    await fetchTrends()
    await fetchOverview()
  } catch (err) {
    console.error('Failed to scan trends', err)
  }
}

async function fetchReports() {
  try {
    const res = await adminService.getResearchReports()
    reportsList.value = res.data || []
  } catch (err) {
    console.error('Failed to load reports', err)
  }
}

async function inspectReport(reportId: string) {
  try {
    activeReport.value = await adminService.getResearchReport(reportId)
    switchTab('workspace')
  } catch (err) {
    console.error('Failed to inspect report', err)
  }
}

async function approveReport(reportId: string) {
  try {
    await adminService.updateResearchReportStatus(reportId, 'editorial_approved', true)
    await fetchReports()
  } catch (err) {
    console.error('Failed to approve report', err)
  }
}

async function verifyEntity(id: string) {
  try {
    await adminService.verifyResearchEntity(id)
    await fetchEntities()
  } catch (err) {
    console.error('Failed to verify entity', err)
  }
}

async function recordFactVerdict(factId: string, status: string) {
  try {
    await adminService.recordFactVerdict(factId, status, 'Editor manual evaluation.')
    await fetchFacts()
  } catch (err) {
    console.error('Failed to record verdict', err)
  }
}

async function launchDeepResearch() {
  if (!newResearch.value.title || !newResearch.value.research_question) return
  isExecutingResearch.value = true
  try {
    const res = await adminService.initiateResearchProject(newResearch.value)
    activeReport.value = res.data.report
  } catch (err) {
    console.error('Failed to execute research', err)
  } finally {
    isExecutingResearch.value = false
  }
}

function openNewEntityModal() {
  switchTab('entities')
}

function openNewResearchModal() {
  switchTab('workspace')
}

function openNewRelationshipModal() {
  // Can be connected to dialog
}

function openReviewQueueModal() {
  // Review queue
}

function openMergeModal(ent: any) {
  const targetId = prompt(`Merge "${ent.canonical_name}" into target entity UUID:`)
  if (targetId) {
    adminService.mergeResearchEntities(targetId, ent.id, 'Manual administrative consolidation.')
      .then(() => fetchEntities())
  }
}

function openSplitModal(ent: any) {
  const newName = prompt(`Split alias from "${ent.canonical_name}" into new entity name:`)
  if (newName) {
    adminService.splitResearchEntity(ent.id, [], newName, 'Manual split into distinct corporate entity.')
      .then(() => fetchEntities())
  }
}

function openAttachEvidenceModal(fact: any) {
  const text = prompt(`Attach supporting or contradicting evidence for: "${fact.claim}"`)
  if (text) {
    adminService.attachFactEvidence(fact.id, {
      source_id: fact.source_id,
      evidence_text: text,
      stance: 'supports',
    }).then(() => fetchFacts())
  }
}

function openNewEventModal() {
  const title = prompt('Event Title:')
  if (title) {
    adminService.createResearchEvent({
      title,
      event_category: 'technology_breakthrough',
      occurred_at: new Date().toISOString(),
    }).then(() => fetchTimeline())
  }
}

function openCorrectDateModal(evt: any) {
  const newDate = prompt(`Correct event date for "${evt.title}" (YYYY-MM-DD):`, evt.occurred_at?.slice(0, 10))
  if (newDate) {
    adminService.correctResearchEventDate(evt.id, newDate, 'Editorial date correction verified from primary filing.')
      .then(() => fetchTimeline())
  }
}

function formatDate(d: string | null): string {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

onMounted(() => {
  const tabParam = route.params.tab as string
  if (tabParam && tabs.some(t => t.id === tabParam)) {
    activeTab.value = tabParam
  }
  loadTabContent(activeTab.value)
})
</script>
