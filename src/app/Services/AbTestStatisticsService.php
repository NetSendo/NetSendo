<?php

namespace App\Services;

class AbTestStatisticsService
{
    /**
     * Calculate the statistical confidence that variant A is better than variant B.
     * Uses a simplified Bayesian approach for quick calculation.
     *
     * @param int $successesA Number of conversions/opens for variant A
     * @param int $totalA Total sends for variant A
     * @param int $successesB Number of conversions/opens for variant B
     * @param int $totalB Total sends for variant B
     * @return float Confidence percentage (0-100)
     */
    public function calculateConfidence(int $successesA, int $totalA, int $successesB, int $totalB): float
    {
        if ($totalA < 10 || $totalB < 10) {
            return 0.0; // Not enough data
        }

        $rateA = $successesA / $totalA;
        $rateB = $successesB / $totalB;

        // Calculate pooled standard error
        $seA = sqrt(($rateA * (1 - $rateA)) / $totalA);
        $seB = sqrt(($rateB * (1 - $rateB)) / $totalB);
        $se = sqrt($seA ** 2 + $seB ** 2);

        if ($se == 0) {
            return $rateA > $rateB ? 100.0 : ($rateA < $rateB ? 0.0 : 50.0);
        }

        // Calculate Z-score
        $z = ($rateA - $rateB) / $se;

        // Convert to confidence using normal distribution CDF
        $confidence = $this->normalCdf($z) * 100;

        return round($confidence, 2);
    }

    /**
     * Calculate Z-score for two proportions.
     *
     * @param float $rateA Rate for variant A (0-1)
     * @param float $rateB Rate for variant B (0-1)
     * @param int $nA Sample size for variant A
     * @param int $nB Sample size for variant B
     * @return float Z-score
     */
    public function calculateZScore(float $rateA, float $rateB, int $nA, int $nB): float
    {
        if ($nA === 0 || $nB === 0) {
            return 0.0;
        }

        // Pooled proportion
        $pooledRate = ($rateA * $nA + $rateB * $nB) / ($nA + $nB);

        // Standard error
        $se = sqrt($pooledRate * (1 - $pooledRate) * (1 / $nA + 1 / $nB));

        if ($se == 0) {
            return 0.0;
        }

        return ($rateA - $rateB) / $se;
    }

    /**
     * Calculate p-value from Z-score (two-tailed).
     *
     * @param float $zScore The Z-score
     * @return float P-value (0-1)
     */
    public function calculatePValue(float $zScore): float
    {
        // Two-tailed p-value
        return 2 * (1 - $this->normalCdf(abs($zScore)));
    }

    /**
     * Calculate confidence interval for a proportion.
     *
     * @param float $rate Success rate (0-1)
     * @param int $n Sample size
     * @param float $confidence Confidence level (0.90, 0.95, 0.99)
     * @return array{lower: float, upper: float}
     */
    public function getConfidenceInterval(float $rate, int $n, float $confidence = 0.95): array
    {
        if ($n === 0) {
            return ['lower' => 0.0, 'upper' => 0.0];
        }

        // Z-value for confidence level
        $zValues = [
            0.90 => 1.645,
            0.95 => 1.96,
            0.99 => 2.576,
        ];
        $z = $zValues[$confidence] ?? 1.96;

        // Standard error
        $se = sqrt(($rate * (1 - $rate)) / $n);

        $margin = $z * $se;

        return [
            'lower' => max(0, round($rate - $margin, 4)),
            'upper' => min(1, round($rate + $margin, 4)),
        ];
    }

    /**
     * Calculate minimum sample size needed for a test.
     *
     * @param float $baselineRate Current baseline rate (0-1)
     * @param float $mde Minimum detectable effect (relative, e.g., 0.1 = 10% improvement)
     * @param float $power Statistical power (typically 0.8)
     * @param float $alpha Significance level (typically 0.05)
     * @return int Minimum sample size per variant
     */
    public function calculateMinimumSampleSize(
        float $baselineRate,
        float $mde = 0.1,
        float $power = 0.8,
        float $alpha = 0.05
    ): int {
        // Expected rate for variant B
        $targetRate = $baselineRate * (1 + $mde);

        // Z-values
        $zAlpha = $this->zValue(1 - $alpha / 2);
        $zBeta = $this->zValue($power);

        // Pooled variance estimate
        $p1 = $baselineRate;
        $p2 = $targetRate;
        $pooled = ($p1 + $p2) / 2;

        $numerator = (
            $zAlpha * sqrt(2 * $pooled * (1 - $pooled)) +
            $zBeta * sqrt($p1 * (1 - $p1) + $p2 * (1 - $p2))
        ) ** 2;

        $denominator = ($p2 - $p1) ** 2;

        if ($denominator === 0.0) {
            return 1000; // Default fallback
        }

        return (int) ceil($numerator / $denominator);
    }

    /**
     * Bayesian probability that A is better than B.
     * Uses Beta distribution approximation.
     *
     * @param int $successesA Successes for variant A
     * @param int $totalA Total trials for variant A
     * @param int $successesB Successes for variant B
     * @param int $totalB Total trials for variant B
     * @param int $simulations Number of Monte Carlo simulations
     * @return float Probability that A beats B (0-1)
     */
    public function calculateBayesianProbability(
        int $successesA,
        int $totalA,
        int $successesB,
        int $totalB,
        int $simulations = 10000
    ): float {
        if ($totalA === 0 || $totalB === 0) {
            return 0.5;
        }

        // Beta distribution parameters (with uniform prior)
        $alphaA = $successesA + 1;
        $betaA = $totalA - $successesA + 1;
        $alphaB = $successesB + 1;
        $betaB = $totalB - $successesB + 1;

        // Monte Carlo simulation
        $aWins = 0;
        for ($i = 0; $i < $simulations; $i++) {
            $sampleA = $this->sampleBeta($alphaA, $betaA);
            $sampleB = $this->sampleBeta($alphaB, $betaB);

            if ($sampleA > $sampleB) {
                $aWins++;
            }
        }

        return $aWins / $simulations;
    }

    /**
     * Get lift (improvement) between two rates.
     *
     * @param float $controlRate Control variant rate
     * @param float $treatmentRate Treatment variant rate
     * @return array{absolute: float, relative: float}
     */
    public function calculateLift(float $controlRate, float $treatmentRate): array
    {
        $absolute = $treatmentRate - $controlRate;
        $relative = $controlRate > 0 ? (($treatmentRate - $controlRate) / $controlRate) * 100 : 0;

        return [
            'absolute' => round($absolute, 4),
            'relative' => round($relative, 2),
        ];
    }

    /**
     * Determine if the test has reached statistical significance.
     *
     * @param int $successesA Successes for variant A
     * @param int $totalA Total trials for variant A
     * @param int $successesB Successes for variant B
     * @param int $totalB Total trials for variant B
     * @param float $confidenceThreshold Threshold percentage (e.g., 95)
     * @return bool
     */
    public function isStatisticallySignificant(
        int $successesA,
        int $totalA,
        int $successesB,
        int $totalB,
        float $confidenceThreshold = 95.0
    ): bool {
        $confidence = $this->calculateConfidence($successesA, $totalA, $successesB, $totalB);
        return $confidence >= $confidenceThreshold || $confidence <= (100 - $confidenceThreshold);
    }

    /**
     * Standard normal cumulative distribution function.
     *
     * @param float $x Input value
     * @return float Probability (0-1)
     */
    protected function normalCdf(float $x): float
    {
        // Approximation using the error function
        $t = 1 / (1 + 0.2316419 * abs($x));
        $d = 0.3989423 * exp(-$x * $x / 2);
        $p = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));

        return $x > 0 ? 1 - $p : $p;
    }

    /**
     * Get Z-value for a given probability.
     *
     * @param float $p Probability (0-1)
     * @return float Z-value
     */
    protected function zValue(float $p): float
    {
        // Approximation using Abramowitz and Stegun formula
        if ($p <= 0) return -10;
        if ($p >= 1) return 10;

        if ($p < 0.5) {
            return -$this->zValue(1 - $p);
        }

        $t = sqrt(-2 * log(1 - $p));
        $c0 = 2.515517;
        $c1 = 0.802853;
        $c2 = 0.010328;
        $d1 = 1.432788;
        $d2 = 0.189269;
        $d3 = 0.001308;

        return $t - ($c0 + $c1 * $t + $c2 * $t * $t) / (1 + $d1 * $t + $d2 * $t * $t + $d3 * $t * $t * $t);
    }

    /**
     * Sample from a Beta distribution.
     * Uses the relationship between Beta and Gamma distributions.
     *
     * @param float $alpha Alpha parameter
     * @param float $beta Beta parameter
     * @return float Sample value (0-1)
     */
    protected function sampleBeta(float $alpha, float $beta): float
    {
        $x = $this->sampleGamma($alpha, 1);
        $y = $this->sampleGamma($beta, 1);

        return $x / ($x + $y);
    }

    /**
     * Sample from a Gamma distribution.
     * Uses Marsaglia and Tsang's method.
     *
     * @param float $shape Shape parameter (k or alpha)
     * @param float $scale Scale parameter (theta)
     * @return float Sample value
     */
    protected function sampleGamma(float $shape, float $scale): float
    {
        if ($shape < 1) {
            return $this->sampleGamma(1 + $shape, $scale) * pow(mt_rand() / mt_getrandmax(), 1 / $shape);
        }

        $d = $shape - 1 / 3;
        $c = 1 / sqrt(9 * $d);

        while (true) {
            do {
                $x = $this->sampleNormal();
                $v = 1 + $c * $x;
            } while ($v <= 0);

            $v = $v * $v * $v;
            $u = mt_rand() / mt_getrandmax();

            if ($u < 1 - 0.0331 * ($x * $x) * ($x * $x)) {
                return $d * $v * $scale;
            }

            if (log($u) < 0.5 * $x * $x + $d * (1 - $v + log($v))) {
                return $d * $v * $scale;
            }
        }
    }

    /**
     * Sample from standard normal distribution.
     * Uses Box-Muller transform.
     *
     * @return float Sample value
     */
    protected function sampleNormal(): float
    {
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }
}
