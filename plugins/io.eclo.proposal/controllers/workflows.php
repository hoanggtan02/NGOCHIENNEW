<?php

class Proposal {
    private $app; // Giả lập đối tượng $app để truy vấn CSDL

     public function __construct($app) {
        $this->app = $app;
    }
    public function findManagerAccountId($account_id) {
        $account_diagram = $this->app->get("proposal_diagram_accounts", "*", ["account" => $account_id, "deleted" => 0]);
        if (!$account_diagram) return null;
        $connection = $this->app->get("proposal_diagram_connections", "*", ["target_node" => $account_diagram['diagram'], "deleted" => 0]);
        if (!$connection) return null;
        $manager_diagram = $this->app->get("proposal_diagram_accounts", "*", ["diagram" => $connection['source_node'], "deleted" => 0]);
        if (!$manager_diagram) return null;
        return (int)$manager_diagram['account'];
    }
    public function buildOrderedPath($all_nodes, $connections) {
        if (empty($all_nodes)) return [];
        $nodes_by_id = [];
        $start_node = null;
        foreach ($all_nodes as $node) {
            $nodes_by_id[$node['id']] = $node;
            if ($node['type'] === 'start') {
                $start_node = $node;
            }
        }
        if ($start_node === null) return null;
        if (empty($connections)) return [$start_node];

        $connections_map = [];
        foreach ($connections as $conn) {
            $connections_map[$conn['source_node']] = $conn['target_node'];
        }

        $ordered_path = [];
        $current_node = $start_node;
        $visited_nodes = [];

        while ($current_node !== null && !isset($visited_nodes[$current_node['id']])) {
            $ordered_path[] = $current_node;
            $visited_nodes[$current_node['id']] = true;
            if ($current_node['type'] === 'finish' || !isset($connections_map[$current_node['id']])) break;
            $next_node_id = $connections_map[$current_node['id']];
            $current_node = $nodes_by_id[$next_node_id] ?? null;
        }
        return $ordered_path;
    }
    public function workflows($proposal_id, $requester_account_id) {
        $proposal = $this->app->get("proposals", "*", ["id" => $proposal_id]);
        if (!$proposal) return ["error" => "Proposal not found"];
        $workflow = $this->app->get("proposal_workflows", "*", ["id" => $proposal['workflows']]);
        if (!$workflow) return ["error" => "Workflow not found"];
        $all_nodes = $this->app->select("proposal_workflows_nodes", "*", ["workflows" => $workflow['id'], "deleted" => 0]);
        $connections = $this->app->select("proposal_workflows_connections", "*", ["workflows" => $workflow['id'], "deleted" => 0]);
        $ordered_nodes = $this->buildOrderedPath($all_nodes, $connections);
        if ($ordered_nodes === null) {
            return ["error" => "Failed to build workflow path. Check start node and connections."];
        }
        $specific_approver_map = [];
        foreach ($all_nodes as $node) {
            if ((int)$node['approval'] === 1 && (int)$node['account'] > 0) {
                $specific_approver_map[(int)$node['account']] = (int)$node['id'];
            }
        }
        // --- BƯỚC 3: XỬ LÝ QUY TRÌNH ĐÃ ĐƯỢC SẮP XẾP ---
        $final_path = [];
        $current_account_id_for_lookup = $requester_account_id;
        $skip_until_node_id = null;
        foreach ($ordered_nodes as $index => $current_node) {
            if ($skip_until_node_id !== null) {
                if ($current_node['id'] != $skip_until_node_id) {
                    continue;
                }
                $skip_until_node_id = null;
            }
            $approver_id = null;
            $approver_type = 'system';
            if ($current_node['type'] === 'approval') {
                // ===== LOGIC MỚI: TỰ ĐỘNG BỎ QUA NẾU NGƯỜI DUYỆT LÀ NGƯỜI ĐỀ XUẤT =====
                if ((int)$current_node['approval'] === 1 && (int)$current_node['account'] === $requester_account_id) {
                    // Tự động bỏ qua bước này vì người duyệt chính là người tạo
                    continue;
                }
                // =======================================================================

                if ((int)$current_node['approval'] === 1) {
                    $approver_id = (int)$current_node['account'];
                    $approver_type = 'specific';
                } elseif ((int)$current_node['approval'] === 2) {
                    $approver_id = $this->findManagerAccountId($current_account_id_for_lookup);
                    $approver_type = 'manager';
                    
                    if ($approver_id !== null && isset($specific_approver_map[$approver_id])) {
                        $target_node_id = $specific_approver_map[$approver_id];
                        $target_node_index = array_search($target_node_id, array_column($ordered_nodes, 'id'));

                        if ($target_node_index !== false && $target_node_index > $index) {
                            $first_intermediate_specific_node = null;
                            $path_slice = array_slice($ordered_nodes, $index + 1, $target_node_index - ($index + 1));

                            foreach ($path_slice as $intermediate_node) {
                                if ((int)$intermediate_node['approval'] === 1) {
                                    $first_intermediate_specific_node = $intermediate_node;
                                    break;
                                }
                            }
                            
                            if ($first_intermediate_specific_node !== null) {
                                $skip_until_node_id = $first_intermediate_specific_node['id'];
                            } else {
                                $skip_until_node_id = $target_node_id;
                            }
                            
                            $current_account_id_for_lookup = $approver_id;
                            continue; 
                        }
                    }
                }
            }
            $final_path[] = [
                'node_id' => (int)$current_node['id'],
                'node_name' => $current_node['name'],
                'type' => $current_node['type'],
                'approval_type' => (int)$current_node['approval'],
                'approver_account_id' => $approver_id ?? $requester_account_id,
                'note' => $approver_type
            ];

            if ($approver_id !== null) {
                $current_account_id_for_lookup = $approver_id;
            }
        }
        return $final_path;
    }
}

?>