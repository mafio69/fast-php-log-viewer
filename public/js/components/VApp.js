/**
 * VApp - root component. Holds all state via store, coordinates props/emits.
 */
window.FPLV = window.FPLV || {};
window.FPLV.components = window.FPLV.components || [];

(function () {
    const F = window.FPLV;
    const store = F.store;

    F.components.push({
        name: 'VApp',
        template: `
        <div class="flex h-screen" :style="{ fontSize: store.fontSize + 'px' }">
            <setup-wizard
                v-if="store.showSetupWizard"
                :store="store"
                @proceed-step="proceedStep"
                @toggle-skip-confirm="v => store.setupSkipConfirm = v"
            ></setup-wizard>
            <sidebar
                :store="store"
                @select-file="selectFile"
                @change-dir="changeDir"
                @load-direct-file="loadDirectFile"
                @refresh-ssh-dir="refreshSSHDir"
                @open-ssh-modal="store.showSSHModal = true"
                @cancel-edit="cancelEdit"
            ></sidebar>
            <ssh-modal
                v-if="store.showSSHModal"
                :store="store"
                @close="store.showSSHModal = false"
                @test-connection="testSSHConnection"
                @add-connection="addSSHConnection"
                @delete-connection="deleteSSHConnection"
                @edit-connection="editSSHConnection"
                @cancel-edit="cancelEdit"
                @connect="connectSSH"
                @execute-connection="executeSSHConnection"
                @cancel-password="cancelPasswordModal"
                @add-manual-file="addManualSSHFile"
                @execute-manual-file-add="executeManualFileAdd"
                @cancel-manual-file="cancelManualFileModal"
            ></ssh-modal>
            <div class="flex-1 flex flex-col overflow-hidden">
                <toolbar
                    :store="store"
                    @toggle-level="toggleLevel"
                    @apply-filters="applyFilters"
                    @load-entries="loadEntries"
                    @update-font-size="v => store.fontSize = v"
                    @go-to-bookmark="goToBookmark"
                    @remove-bookmark="removeBookmark"
                    @toggle-bookmarks="store.showBookmarks = !store.showBookmarks"
                    @toggle-level-filters="store.showLevelFilters = !store.showLevelFilters"
                ></toolbar>
                <data-table
                    :store="store"
                    @toggle-table-sort="toggleTableSort"
                    @set-table-page="setTablePage"
                    @set-table-page-size="setTablePageSize"
                    @table-prev-page="tablePrevPage"
                    @table-next-page="tableNextPage"
                    @toggle-expand="toggle"
                    @toggle-bookmark="toggleBookmark"
                ></data-table>
            </div>
        </div>
        `,
        setup() {
            return {
                store,
                proceedStep: F.proceedStep,
                selectFile: F.selectFile,
                changeDir: F.changeDir,
                loadDirectFile: F.loadDirectFile,
                refreshSSHDir: F.refreshSSHDir,
                cancelEdit: F.cancelEdit,
                testSSHConnection: F.testSSHConnection,
                addSSHConnection: F.addSSHConnection,
                deleteSSHConnection: F.deleteSSHConnection,
                editSSHConnection: F.editSSHConnection,
                connectSSH: F.connectSSH,
                executeSSHConnection: F.executeSSHConnection,
                cancelPasswordModal: F.cancelPasswordModal,
                addManualSSHFile: F.addManualSSHFile,
                executeManualFileAdd: F.executeManualFileAdd,
                cancelManualFileModal: F.cancelManualFileModal,
                toggleLevel: F.toggleLevel,
                applyFilters: F.applyFilters,
                loadEntries: F.loadEntries,
                goToBookmark: F.goToBookmark,
                removeBookmark: F.removeBookmark,
                toggleTableSort: F.toggleTableSort,
                setTablePage: F.setTablePage,
                setTablePageSize: F.setTablePageSize,
                tablePrevPage: F.tablePrevPage,
                tableNextPage: F.tableNextPage,
                toggle: F.toggle,
                toggleBookmark: F.toggleBookmark,
            };
        }
    });
})();
