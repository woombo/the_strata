/**
 * @file
 * JavaScript for Strata Boards drag-and-drop functionality.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.strataBoards = {
    attach: function (context, settings) {
      const columns = once('strata-board-columns', '.strata-board__column-content', context);
      const tickets = once('strata-board-tickets', '.strata-ticket-card', context);

      if (!columns.length) {
        return;
      }

      let draggedElement = null;
      let draggedPlaceholder = null;
      let sourceColumn = null;

      // Create placeholder element for drop position indicator.
      function createPlaceholder() {
        const placeholder = document.createElement('div');
        placeholder.className = 'strata-ticket-card strata-ticket-card--placeholder';
        placeholder.style.height = '60px';
        placeholder.style.background = 'rgba(0, 82, 204, 0.1)';
        placeholder.style.border = '2px dashed rgba(0, 82, 204, 0.4)';
        placeholder.style.borderRadius = '6px';
        placeholder.style.marginBottom = '0.5rem';
        return placeholder;
      }

      // Get the element after which we should insert (based on mouse position).
      function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.strata-ticket-card:not(.dragging):not(.strata-ticket-card--placeholder)')];

        return draggableElements.reduce((closest, child) => {
          const box = child.getBoundingClientRect();
          const offset = y - box.top - box.height / 2;
          if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
          } else {
            return closest;
          }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
      }

      // Setup drag events on tickets.
      tickets.forEach(function (ticket) {
        ticket.addEventListener('dragstart', function (e) {
          draggedElement = this;
          sourceColumn = this.closest('.strata-board__column-content');
          this.classList.add('dragging');

          // Create placeholder.
          draggedPlaceholder = createPlaceholder();

          // Set drag data.
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/plain', this.dataset.ticketId);

          // Delay adding styles for visual feedback.
          setTimeout(function () {
            ticket.style.opacity = '0.4';
          }, 0);
        });

        ticket.addEventListener('dragend', function (e) {
          this.classList.remove('dragging');
          this.style.opacity = '1';

          // Remove placeholder if exists.
          if (draggedPlaceholder && draggedPlaceholder.parentNode) {
            draggedPlaceholder.parentNode.removeChild(draggedPlaceholder);
          }

          draggedElement = null;
          draggedPlaceholder = null;
          sourceColumn = null;

          // Remove drag-over class from all columns.
          document.querySelectorAll('.strata-board__column-content').forEach(function (col) {
            col.classList.remove('drag-over');
          });
        });
      });

      // Setup drop zones on columns.
      columns.forEach(function (column) {
        column.addEventListener('dragover', function (e) {
          e.preventDefault();
          e.dataTransfer.dropEffect = 'move';
          this.classList.add('drag-over');

          if (!draggedElement || !draggedPlaceholder) {
            return;
          }

          const afterElement = getDragAfterElement(this, e.clientY);

          // Move placeholder to indicate drop position.
          if (afterElement) {
            this.insertBefore(draggedPlaceholder, afterElement);
          } else {
            this.appendChild(draggedPlaceholder);
          }
        });

        column.addEventListener('dragleave', function (e) {
          // Only remove class if leaving the column entirely.
          if (!this.contains(e.relatedTarget)) {
            this.classList.remove('drag-over');
            // Remove placeholder when leaving column.
            if (draggedPlaceholder && draggedPlaceholder.parentNode === this) {
              this.removeChild(draggedPlaceholder);
            }
          }
        });

        column.addEventListener('drop', function (e) {
          e.preventDefault();
          this.classList.remove('drag-over');

          if (!draggedElement) {
            return;
          }

          const targetColumn = this;
          const newStatusId = targetColumn.dataset.columnId;

          // Insert the dragged element at the placeholder position.
          if (draggedPlaceholder && draggedPlaceholder.parentNode) {
            draggedPlaceholder.parentNode.insertBefore(draggedElement, draggedPlaceholder);
            draggedPlaceholder.parentNode.removeChild(draggedPlaceholder);
          } else {
            targetColumn.appendChild(draggedElement);
          }

          // Update column counts.
          updateColumnCounts();

          // Get all ticket IDs in the target column in their new order.
          const ticketIds = getColumnTicketIds(targetColumn);

          // Send AJAX request to update the order and status.
          updateTicketOrder(newStatusId, ticketIds);
        });
      });

      /**
       * Gets all ticket IDs in a column in their current DOM order.
       *
       * @param {Element} column - The column element.
       * @returns {Array} - Array of ticket IDs.
       */
      function getColumnTicketIds(column) {
        const ticketCards = column.querySelectorAll('.strata-ticket-card:not(.strata-ticket-card--placeholder)');
        return Array.from(ticketCards).map(function (card) {
          return card.dataset.ticketId;
        });
      }

      /**
       * Updates the ticket count displayed in column headers.
       */
      function updateColumnCounts() {
        document.querySelectorAll('.strata-board__column').forEach(function (column) {
          const content = column.querySelector('.strata-board__column-content');
          const countEl = column.querySelector('.strata-board__column-count');
          if (content && countEl) {
            const ticketCount = content.querySelectorAll('.strata-ticket-card:not(.strata-ticket-card--placeholder)').length;
            countEl.textContent = ticketCount;
          }
        });
      }

      /**
       * Sends an AJAX request to update the ticket order within a column.
       *
       * @param {string} columnId - The column term ID.
       * @param {Array} ticketIds - Array of ticket IDs in order.
       */
      function updateTicketOrder(columnId, ticketIds) {
        const url = Drupal.url('api/strata-boards/column/' + columnId + '/order');

        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            ticket_ids: ticketIds
          })
        })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(function (data) {
          if (data.success) {
            Drupal.announce(Drupal.t('Ticket order updated successfully.'));
          } else {
            console.error('Failed to update ticket order:', data.error);
            Drupal.announce(Drupal.t('Failed to update ticket order.'));
          }
        })
        .catch(function (error) {
          console.error('Error updating ticket order:', error);
          Drupal.announce(Drupal.t('Error updating ticket order. Please try again.'));
        });
      }
    }
  };

})(Drupal, drupalSettings, once);
