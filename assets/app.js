import './styles/app.css';

import { Application } from '@hotwired/stimulus';
import { Turbo } from '@hotwired/turbo';

import CalendarController from './controllers/calendar_controller.js';
import SlotMonitorController from './controllers/slot_monitor_controller.js';

Turbo.session.drive = true;

const application = Application.start();
application.register('calendar', CalendarController);
application.register('slot-monitor', SlotMonitorController);

export default application;
