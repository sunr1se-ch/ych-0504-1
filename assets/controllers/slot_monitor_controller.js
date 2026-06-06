import { Controller } from '@hotwired/stimulus';
import { visit } from '@hotwired/turbo';

export default class extends Controller {
    static targets = ['statusCard', 'co2Chart', 'waitlistTable'];
    static values = {
        slotId: String,
        sseUrl: String,
    };

    connect() {
        if (this.slotIdValue === 'global') {
            return;
        }

        this.eventSource = null;
        this.connectSSE();
    }

    disconnect() {
        this.disconnectSSE();
    }

    connectSSE() {
        if (!this.sseUrlValue) {
            return;
        }

        this.disconnectSSE();

        this.eventSource = new EventSource(this.sseUrlValue);

        this.eventSource.addEventListener('update', (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleUpdate(data);
            } catch (e) {
                console.error('Failed to parse SSE data:', e);
            }
        });

        this.eventSource.addEventListener('error', (event) => {
            console.warn('SSE connection error:', event);
            if (this.eventSource.readyState === EventSource.CLOSED) {
                setTimeout(() => this.connectSSE(), 3000);
            }
        });

        this.eventSource.addEventListener('open', () => {
            console.log('SSE connection established');
        });
    }

    disconnectSSE() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    handleUpdate(data) {
        if (data.booking) {
            this.updateStatusCard(data.booking);
        }

        if (data.latestCo2Reading) {
            this.updateCo2Chart(data.latestCo2Reading);
        }

        if (data.occupancy) {
            this.updateCalendarSlots(data);
        }

        this.animateUpdate();
    }

    updateStatusCard(booking) {
        if (!this.hasStatusCardTarget) {
            return;
        }

        const statusBadge = this.statusCardTarget.querySelector('.booking-status');
        if (statusBadge) {
            const oldStatus = statusBadge.className.match(/status-(\w+)/)?.[1];
            if (oldStatus !== booking.status) {
                statusBadge.className = statusBadge.className.replace(
                    /status-\w+/,
                    `status-${booking.status}`
                );
                statusBadge.textContent = booking.status.charAt(0).toUpperCase() + booking.status.slice(1);
                this.flashElement(statusBadge);
            }
        }
    }

    updateCo2Chart(reading) {
        if (!this.hasCo2ChartTarget) {
            return;
        }

        const barsContainer = this.co2ChartTarget.querySelector('.chart-bars');
        if (!barsContainer) {
            return;
        }

        const maxCo2 = Math.max(...Array.from(barsContainer.querySelectorAll('.bar')).map((bar) => {
            const height = parseFloat(bar.style.height);
            const yMax = this.calculateYMax();
            return (height / 100) * yMax;
        }), reading.co2Ppm);

        const yMax = this.calculateYMax(maxCo2);

        const existingBars = barsContainer.querySelectorAll('.bar-wrapper');
        if (existingBars.length > 20) {
            existingBars[0].remove();
        }

        const height = (reading.co2Ppm / yMax) * 100;
        const time = new Date(reading.readAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        const barWrapper = document.createElement('div');
        barWrapper.className = 'bar-wrapper';
        barWrapper.title = `${reading.co2Ppm} ppm at ${time}`;
        barWrapper.innerHTML = `
            <div class="bar" style="height: ${height}%"></div>
            <div class="bar-label">${time}</div>
        `;

        barsContainer.appendChild(barWrapper);
        this.flashElement(barWrapper.querySelector('.bar'));

        this.updateYAxis(yMax);
    }

    calculateYMax(currentMax = 0) {
        if (currentMax === 0) {
            const yTicks = this.co2ChartTarget?.querySelectorAll('.y-tick');
            if (yTicks && yTicks.length > 0) {
                return parseInt(yTicks[0].textContent || '1000');
            }
            return 1000;
        }
        return Math.ceil(currentMax / 1000) * 1000 + 1000;
    }

    updateYAxis(yMax) {
        const yAxis = this.co2ChartTarget?.querySelector('.chart-y-axis');
        if (!yAxis) {
            return;
        }

        const yStep = yMax / 4;
        const yTicks = yAxis.querySelectorAll('.y-tick');
        yTicks.forEach((tick, index) => {
            tick.textContent = Math.round(yStep * (yTicks.length - 1 - index));
        });

        const bars = this.co2ChartTarget.querySelectorAll('.bar');
        bars.forEach((bar) => {
            const currentHeight = parseFloat(bar.style.height);
            const currentMax = this.calculateYMax();
            const value = (currentHeight / 100) * currentMax;
            const newHeight = (value / yMax) * 100;
            bar.style.height = `${newHeight}%`;
        });
    }

    updateCalendarSlots(data) {
        const slotElements = document.querySelectorAll(`[data-slot-id="${data.slotId}"]`);
        slotElements.forEach((element) => {
            const statusClasses = ['status-available', 'status-pending', 'status-active', 'status-failed', 'status-done'];

            let newStatus = 'available';
            if (data.booking) {
                newStatus = data.booking.status;
            } else if (!data.isOpen) {
                newStatus = 'done';
            }

            const newStatusClass = `status-${newStatus}`;

            statusClasses.forEach((cls) => {
                element.classList.remove(cls);
            });
            element.classList.add(newStatusClass);

            const statusBadge = element.querySelector('.status-badge');
            if (statusBadge) {
                statusClasses.forEach((cls) => {
                    statusBadge.classList.remove(cls);
                });
                statusBadge.classList.add(newStatusClass);
                statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            }

            const waitlistCount = element.querySelector('.waitlist-count');
            if (waitlistCount && data.occupancy?.waitlistCount !== undefined) {
                waitlistCount.textContent = data.occupancy.waitlistCount;
            }

            this.flashElement(element);
        });
    }

    flashElement(element) {
        element.style.transition = 'box-shadow 0.3s ease, transform 0.3s ease';
        element.style.boxShadow = '0 0 10px 3px rgba(59, 130, 246, 0.5)';
        element.style.transform = 'scale(1.02)';

        setTimeout(() => {
            element.style.boxShadow = '';
            element.style.transform = '';
        }, 500);
    }

    animateUpdate() {
        this.element.style.transition = 'opacity 0.3s ease';
        this.element.style.opacity = '0.8';

        setTimeout(() => {
            this.element.style.opacity = '1';
        }, 150);
    }

    async forceFlush(event) {
        event.preventDefault();

        if (!confirm('Are you sure you want to force flush this slot?')) {
            return;
        }

        try {
            const response = await fetch(`/api/slots/${this.slotIdValue}/force-flush`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                this.showNotification('Flush initiated successfully', 'success');
            } else {
                this.showNotification('Failed to initiate flush', 'error');
            }
        } catch (error) {
            console.error('Force flush error:', error);
            this.showNotification('An error occurred', 'error');
        }
    }

    async cancelBooking(event) {
        event.preventDefault();

        if (!confirm('Are you sure you want to cancel this booking?')) {
            return;
        }

        try {
            const response = await fetch(`/api/bookings/${this.slotIdValue}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                this.showNotification('Booking cancelled successfully', 'success');
                visit(window.location.href);
            } else {
                this.showNotification('Failed to cancel booking', 'error');
            }
        } catch (error) {
            console.error('Cancel booking error:', error);
            this.showNotification('An error occurred', 'error');
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s forwards;
            ${type === 'success' ? 'background-color: #10b981;' : ''}
            ${type === 'error' ? 'background-color: #ef4444;' : ''}
            ${type === 'info' ? 'background-color: #3b82f6;' : ''}
        `;

        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
            style.remove();
        }, 3000);
    }
}
