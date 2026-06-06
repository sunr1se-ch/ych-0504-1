import { Controller } from '@hotwired/stimulus';
import { visit } from '@hotwired/turbo';

export default class extends Controller {
    static targets = ['calendar', 'prevBtn', 'nextBtn', 'currentDate'];
    static values = {
        startDate: String,
        endDate: String,
    };

    connect() {
        this.updateDateDisplay();
    }

    prevWeek(event) {
        event.preventDefault();
        const currentStart = new Date(this.startDateValue);
        const newStart = new Date(currentStart);
        newStart.setDate(newStart.getDate() - 7);
        const newEnd = new Date(newStart);
        newEnd.setDate(newEnd.getDate() + 6);

        this.navigateTo(newStart, newEnd);
    }

    nextWeek(event) {
        event.preventDefault();
        const currentStart = new Date(this.startDateValue);
        const newStart = new Date(currentStart);
        newStart.setDate(newStart.getDate() + 7);
        const newEnd = new Date(newStart);
        newEnd.setDate(newEnd.getDate() + 6);

        this.navigateTo(newStart, newEnd);
    }

    goToDate(event) {
        const dateInput = event.target;
        const selectedDate = new Date(dateInput.value);

        if (isNaN(selectedDate.getTime())) {
            return;
        }

        const dayOfWeek = selectedDate.getDay();
        const diff = selectedDate.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
        const newStart = new Date(selectedDate.setDate(diff));
        const newEnd = new Date(newStart);
        newEnd.setDate(newEnd.getDate() + 6);

        this.navigateTo(newStart, newEnd);
    }

    navigateTo(startDate, endDate) {
        const startStr = this.formatDate(startDate);
        const endStr = this.formatDate(endDate);

        const url = new URL(window.location.href);
        url.searchParams.set('startDate', startStr);
        url.searchParams.set('endDate', endStr);

        this.startDateValue = startStr;
        this.endDateValue = endStr;

        this.updateDateDisplay();

        if (this.hasCalendarTarget) {
            visit(url.toString(), { frame: 'calendar-frame' });
        } else {
            visit(url.toString());
        }

        window.history.pushState({}, '', url.toString());
    }

    updateDateDisplay() {
        if (!this.hasCurrentDateTarget) {
            return;
        }

        const start = new Date(this.startDateValue);
        const end = new Date(this.endDateValue);

        const options = { month: 'short', day: 'numeric', year: 'numeric' };
        const startFormatted = start.toLocaleDateString(undefined, options);
        const endFormatted = end.toLocaleDateString(undefined, options);

        this.currentDateTargets.forEach((target) => {
            if (target.tagName === 'SPAN') {
                target.textContent = `${startFormatted} - ${endFormatted}`;
            } else if (target.tagName === 'INPUT') {
                target.value = this.startDateValue;
            }
        });

        if (this.hasPrevBtnTarget) {
            const prevStart = new Date(this.startDateValue);
            prevStart.setDate(prevStart.getDate() - 7);
            const prevEnd = new Date(prevStart);
            prevEnd.setDate(prevEnd.getDate() + 6);

            const prevUrl = new URL(window.location.pathname, window.location.origin);
            prevUrl.searchParams.set('startDate', this.formatDate(prevStart));
            prevUrl.searchParams.set('endDate', this.formatDate(prevEnd));
            this.prevBtnTarget.href = prevUrl.toString();
        }

        if (this.hasNextBtnTarget) {
            const nextStart = new Date(this.startDateValue);
            nextStart.setDate(nextStart.getDate() + 7);
            const nextEnd = new Date(nextStart);
            nextEnd.setDate(nextEnd.getDate() + 6);

            const nextUrl = new URL(window.location.pathname, window.location.origin);
            nextUrl.searchParams.set('startDate', this.formatDate(nextStart));
            nextUrl.searchParams.set('endDate', this.formatDate(nextEnd));
            this.nextBtnTarget.href = nextUrl.toString();
        }
    }

    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
}
