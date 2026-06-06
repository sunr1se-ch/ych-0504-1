# 平菇种植架 CO2 置换时段预约系统

基于 Symfony 7 + Doctrine + PostgreSQL 的食用菌种植 CO2 自动置换管理系统。

## 功能概述

每个种植架（Rack）有固定体积和基准 CO2 浓度。用户可预约冲洗时段（FlushSlot），到时自动开启置换阀，
探针每 2 分钟读取一次 CO2 浓度，若 20 分钟内未降到基准值的 60%，则预约失败并释放给候补队列。

## 核心实体

| 实体 | 说明 |
|------|------|
| **Rack** | 种植架，包含 `volume_m3`（体积）、`baseline_co2_ppm`（基准 CO2 浓度） |
| **FlushSlot** | 冲洗时段，包含开始时间、时长、开放状态 |
| **Booking** | 预约记录，状态：`pending` / `active` / `failed` / `done` |
| **ProbeReading** | 探针读数，每 2 分钟记录一次 CO2 浓度 |
| **Waitlist** | 候补队列，按优先级分配释放的时段 |

## API 端点

### 预订接口
```http
POST /api/racks/{id}/book
Content-Type: application/json

{
    "userName": "张三",
    "slotId": 1,
    "startTime": "2026-06-05T10:00:00+08:00",
    "durationMinutes": 20
}
```

**响应状态码：**
- `201 Created` - 预订成功
- `202 Accepted` - 已加入候补队列
- `400 Bad Request` - 参数验证失败
- `404 Not Found` - 种植架不存在
- `409 Conflict` - 时段已被占用

### 实时事件流 (SSE)
```http
GET /api/slots/{id}/events
Content-Type: text/event-stream
```

每 2 秒推送一次更新，包含当前预订状态、最新 CO2 读数。

### 其他接口
```http
GET    /api/racks              # 列出所有种植架
GET    /api/racks/{id}         # 获取种植架详情
GET    /api/racks/{id}/slots   # 获取种植架所有时段
```

### 管理后台
```
GET /admin/slots               # 日历视图（周视图）
GET /admin/slots/{id}/show     # 时段详情
```

## 队列拓扑

```
┌──────────────────────────────────────────────────────────────────────────┐
│                              RabbitMQ                                    │
├─────────────────┬────────────────────────────────────────────────────────┤
│                 │                                                        │
│  ┌──────────┐   │   ┌──────────────┐     ┌──────────────────────┐       │
│  │  async   │───┼──▶│  Worker      │────▶│  Booking CRUD /       │       │
│  │  queue   │   │   │  (messenger) │     │  Background tasks    │       │
│  └──────────┘   │   └──────────────┘     └──────────────────────┘       │
│                 │                                                        │
│  ┌──────────┐   │   ┌──────────────┐     ┌──────────────────────┐       │
│  │ evaluate │───┼──▶│  Messenger   │────▶│  CO2 Evaluation      │       │
│  │  _flush   │   │   │  Consumer    │     │  (every 2 minutes)   │       │
│  │  queue    │   │   │  (dedicated) │     │  - Read probe        │       │
│  └──────────┘   │   └──────────────┘     │  - Check target      │       │
│                 │                         │  - Retry / Fail      │       │
│  ┌──────────┐   │                         │  - Promote waitlist  │       │
│  │  failed  │   │   ┌──────────────┐     └───────────┬──────────┘       │
│  │  queue   │◀──┼───│  Doctrine    │                 │                  │
│  │ (doctrine)│   │   │  Storage    │                 │                  │
│  └──────────┘   │   └──────────────┘                 │                  │
│                 │                                      │                  │
└─────────────────┴──────────────────────────────────────┼──────────────────┘
                                                         │
                                                         ▼
                                                 ┌─────────────────┐
                                                 │  SSE Stream     │
                                                 │  /api/slots/{id}/events
                                                 └─────────────────┘
                                                         │
                                                         ▼
                                                 ┌─────────────────┐
                                                 │  Admin Calendar │
                                                 │  /admin/slots   │
                                                 └─────────────────┘
```

### 消息流说明

1. **预订请求** → `POST /api/racks/{id}/book` → 创建 Booking → 状态 `pending`
2. **定时检查** → `BookingEventSubscriber` 检查到开始时间 → 调用 `FlushSlotManager::openValve()`
3. **发送评估消息** → `EvaluateFlushMessage` → `evaluate_flush` 队列
4. **评估循环**：
   - Consumer 接收消息
   - 生成 ProbeReading（模拟 CO2 读数）
   - 检查是否达到目标（baseline × 0.6）
   - 未达标且未超时 → 延迟 2 分钟后重新投递（`DelayStamp`）
   - 达标 → 标记 `done`
   - 超时（20 分钟）→ 标记 `failed` → 从 Waitlist 提升候补
5. **幂等性保障**：每次处理先检查 Booking 状态，非 `active` 直接返回

## Docker Compose 服务

| 服务 | 镜像 | 端口 | 说明 |
|------|------|------|------|
| **php** | `php:8.2-fpm-alpine` | - | PHP-FPM 应用服务 |
| **worker** | 同上 | - | Messenger async 队列消费者 |
| **messenger-consumer** | 同上 | - | evaluate_flush 专用消费者 |
| **db** | `postgres:15-alpine` | 5432 | PostgreSQL 数据库 |
| **nginx** | `nginx:alpine` | 80 | Web 服务器 |
| **rabbitmq** | `rabbitmq:3-management-alpine` | 5672, 15672 | 消息队列 |

## 快速开始

```bash
# 1. 克隆并进入项目
git clone <repo> && cd <project>

# 2. 构建并启动服务
docker-compose up -d --build

# 3. 安装依赖
docker-compose exec php composer install

# 4. 创建数据库表
docker-compose exec php php bin/console doctrine:migrations:migrate -n

# 5. 加载测试数据
docker-compose exec php php bin/console doctrine:fixtures:load -n

# 6. 安装前端依赖
docker-compose exec php php bin/console importmap:install

# 7. 构建前端资源
docker-compose exec php php bin/console asset-map:compile
```

访问地址：
- 应用: http://localhost
- 管理后台: http://localhost/admin/slots
- RabbitMQ 管理: http://localhost:15672 (guest/guest)

## 故障判定幂等性设计

`EvaluateFlushMessageHandler` 的幂等性保障：

```php
// 第 51-58 行：状态检查
if ($booking->getStatus() !== 'active') {
    $this->logger->info('Booking already processed, skipping', [
        'booking_id' => $booking->getId(),
        'current_status' => $booking->getStatus(),
    ]);
    return;
}
```

- 消息可能被重复投递（网络问题、Consumer 重启等）
- 每次处理前检查状态，确保只有 `active` 状态的预订会被处理
- 状态变更为 `done` 或 `failed` 后，重复消息会被安全忽略
- 所有数据库操作在事务中执行，确保原子性

## 技术栈

- **后端**: Symfony 7, Doctrine ORM, Messenger
- **数据库**: PostgreSQL 15
- **消息队列**: RabbitMQ
- **前端**: Twig, Stimulus, Turbo (无 React/Vue)
- **实时通信**: SSE (Server-Sent Events)
- **容器化**: Docker, Docker Compose
- **Web 服务器**: Nginx

## 配置说明

### 环境变量 (.env)
```env
DATABASE_URL="postgresql://symfony:symfony@db:5432/symfony?serverVersion=15"
MESSENGER_TRANSPORT_DSN="amqp://guest:guest@rabbitmq:5672/%2f"
```

### 关键参数
| 参数 | 默认值 | 说明 |
|------|--------|------|
| 目标 CO2 比例 | 0.6 | 需降到 baseline × 0.6 |
| 评估间隔 | 2 分钟 | Probe 读数频率 |
| 超时时间 | 20 分钟 | 未达标则标记 failed |
| SSE 超时 | 300 秒 | 事件流最长连接时间 |

## 开发命令

```bash
# 运行消息消费
docker-compose exec php php bin/console messenger:consume async -vv

# 运行 CO2 评估消费
docker-compose exec php php bin/console messenger:consume evaluate_flush -vv

# 查看失败消息
docker-compose exec php php bin/console messenger:failed:show

# 重试失败消息
docker-compose exec php php bin/console messenger:failed:retry

# 查看 Doctrine 映射
docker-compose exec php php bin/console doctrine:schema:validate
```
